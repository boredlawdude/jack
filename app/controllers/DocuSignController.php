<?php
declare(strict_types=1);

/**
 * DocuSignController
 *
 * Handles DocuSign Authorization Code Grant OAuth flow and envelope management.
 *
 * Required .env keys:
 *   DOCUSIGN_CLIENT_ID       – Integration Key from the DocuSign Apps & Keys page
 *   DOCUSIGN_CLIENT_SECRET   – Client Secret for the integration key
 *   DOCUSIGN_REDIRECT_URI    – Must match exactly what is registered in DocuSign
 *                              e.g. https://yourapp.example.com/index.php?page=docusign_callback
 *   DOCUSIGN_WEBHOOK_HMAC_KEY – (optional) HMAC key configured in DocuSign Connect
 *
 * Sandbox auth server: https://account-d.docusign.com
 * Sandbox API base:    https://demo.docusign.net
 */
class DocuSignController
{
    private \PDO $db;

    public function __construct()
    {
        $this->db = db();
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function config(): array
    {
        return [
            'client_id'    => (string)($_ENV['DOCUSIGN_CLIENT_ID']       ?? ''),
            'client_secret'=> (string)($_ENV['DOCUSIGN_CLIENT_SECRET']    ?? ''),
            'redirect_uri' => (string)($_ENV['DOCUSIGN_REDIRECT_URI']     ?? ''),
            'auth_server'  => 'https://account-d.docusign.com',
            'webhook_hmac' => (string)($_ENV['DOCUSIGN_WEBHOOK_HMAC_KEY'] ?? ''),
        ];
    }

    /**
     * Returns a valid access token from session, attempting a refresh if expired.
     * Returns null if no token is available or the refresh fails.
     */
    private function getToken(): ?string
    {
        if (empty($_SESSION['ds_access_token']) || empty($_SESSION['ds_token_expiry'])) {
            return null;
        }
        // Refresh 60 seconds before expiry to avoid racing edge cases
        if (time() >= (int)$_SESSION['ds_token_expiry'] - 60) {
            if (!$this->refreshToken()) {
                return null;
            }
        }
        return $_SESSION['ds_access_token'];
    }

    private function storeToken(array $data): void
    {
        $_SESSION['ds_access_token']  = $data['access_token'];
        $_SESSION['ds_token_expiry']  = time() + (int)($data['expires_in'] ?? 3600);
        if (!empty($data['refresh_token'])) {
            $_SESSION['ds_refresh_token'] = $data['refresh_token'];
        }
    }

    private function refreshToken(): bool
    {
        if (empty($_SESSION['ds_refresh_token'])) {
            return false;
        }
        $cfg = $this->config();
        $ch = curl_init($cfg['auth_server'] . '/oauth/token');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query([
                'grant_type'    => 'refresh_token',
                'refresh_token' => $_SESSION['ds_refresh_token'],
            ]),
            CURLOPT_HTTPHEADER     => [
                'Authorization: Basic ' . base64_encode($cfg['client_id'] . ':' . $cfg['client_secret']),
                'Content-Type: application/x-www-form-urlencoded',
            ],
            CURLOPT_TIMEOUT        => 15,
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code !== 200) {
            return false;
        }
        $data = json_decode((string)$resp, true);
        if (empty($data['access_token'])) {
            return false;
        }
        $this->storeToken($data);
        return true;
    }

    /**
     * Calls /oauth/userinfo to discover the account_id and base_uri,
     * then stores them in the session.
     */
    private function fetchAndStoreUserInfo(string $token): bool
    {
        $cfg = $this->config();
        $ch = curl_init($cfg['auth_server'] . '/oauth/userinfo');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $token],
            CURLOPT_TIMEOUT        => 15,
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code !== 200) {
            return false;
        }
        $info = json_decode((string)$resp, true);
        $account = null;
        foreach ((array)($info['accounts'] ?? []) as $a) {
            if (!empty($a['is_default'])) {
                $account = $a;
                break;
            }
        }
        if ($account === null && !empty($info['accounts'])) {
            $account = $info['accounts'][0];
        }
        if ($account === null) {
            return false;
        }
        $_SESSION['ds_account_id'] = $account['account_id'];
        $_SESSION['ds_base_uri']   = rtrim((string)$account['base_uri'], '/');
        return true;
    }

    private function loadDocument(int $docId): array|false
    {
        $stmt = $this->db->prepare("
            SELECT cd.*,
                   c.contract_id, c.name AS contract_name, c.contract_number,
                   c.department_id,
                   CONCAT(cp.first_name, ' ', cp.last_name) AS counterparty_name,
                   cp.email AS counterparty_email,
                   CONCAT(op.first_name, ' ', op.last_name) AS owner_name,
                   op.email AS owner_email,
                   oc.name AS owner_company_name,
                   cc.name AS counterparty_company_name,
                   d.department_head_id,
                   CONCAT(dh.first_name, ' ', dh.last_name) AS dept_head_name,
                   dh.email AS dept_head_email
            FROM contract_documents cd
            JOIN contracts c ON c.contract_id = cd.contract_id
            LEFT JOIN people cp ON cp.person_id = c.counterparty_primary_contact_id
            LEFT JOIN people op ON op.person_id = c.owner_primary_contact_id
            LEFT JOIN companies oc ON oc.company_id = c.owner_company_id
            LEFT JOIN companies cc ON cc.company_id = c.counterparty_company_id
            LEFT JOIN departments d ON d.department_id = c.department_id
            LEFT JOIN people dh ON dh.person_id = d.department_head_id
            WHERE cd.contract_document_id = :id
        ");
        $stmt->execute([':id' => $docId]);
        return $stmt->fetch();
    }

    /**
     * Returns people who hold a given role_key as [{name, email}].
     */
    private function getPeopleByRoleKey(string $roleKey): array
    {
        $stmt = $this->db->prepare("
            SELECT CONCAT(p.first_name, ' ', p.last_name) AS name, p.email
            FROM people p
            JOIN person_roles pr ON pr.person_id = p.person_id
            JOIN roles r ON r.role_id = pr.role_id
            WHERE r.role_key = ? AND p.email IS NOT NULL AND p.email <> ''
            ORDER BY p.last_name, p.first_name
        ");
        $stmt->execute([$roleKey]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // -------------------------------------------------------------------------
    // Action: Initiate OAuth
    // GET  /index.php?page=docusign_auth&doc_id=X&contract_id=Y
    // -------------------------------------------------------------------------

    public function initiateAuth(): void
    {
        $docId      = (int)($_GET['doc_id']      ?? 0);
        $contractId = (int)($_GET['contract_id'] ?? 0);

        if ($docId <= 0 || $contractId <= 0) {
            http_response_code(400);
            exit('Invalid parameters.');
        }

        $_SESSION['ds_pending_doc_id']      = $docId;
        $_SESSION['ds_pending_contract_id'] = $contractId;

        // If already authenticated and token is still valid, skip OAuth
        if ($this->getToken() !== null && !empty($_SESSION['ds_account_id'])) {
            header('Location: /index.php?page=docusign_send');
            exit;
        }

        $cfg   = $this->config();
        $state = bin2hex(random_bytes(16));
        $_SESSION['ds_oauth_state'] = $state;

        $authUrl = $cfg['auth_server'] . '/oauth/auth?' . http_build_query([
            'response_type' => 'code',
            'scope'         => 'signature',
            'client_id'     => $cfg['client_id'],
            'redirect_uri'  => $cfg['redirect_uri'],
            'state'         => $state,
        ]);

        header('Location: ' . $authUrl);
        exit;
    }

    // -------------------------------------------------------------------------
    // Action: OAuth callback
    // GET  /index.php?page=docusign_callback
    // -------------------------------------------------------------------------

    public function handleCallback(): void
    {
        // CSRF check
        $state = (string)($_GET['state'] ?? '');
        if (
            empty($_SESSION['ds_oauth_state']) ||
            !hash_equals((string)$_SESSION['ds_oauth_state'], $state)
        ) {
            http_response_code(400);
            exit('Invalid OAuth state parameter.');
        }
        unset($_SESSION['ds_oauth_state']);

        // Handle user-denied or error responses from DocuSign
        if (!empty($_GET['error'])) {
            $errDesc = htmlspecialchars((string)($_GET['error_description'] ?? $_GET['error']), ENT_QUOTES, 'UTF-8');
            $contractId = (int)($_SESSION['ds_pending_contract_id'] ?? 0);
            unset($_SESSION['ds_pending_doc_id'], $_SESSION['ds_pending_contract_id']);
            $_SESSION['docusign_flash_error'] = 'DocuSign authorization failed: ' . $errDesc;
            header('Location: /index.php?page=contracts_show&contract_id=' . $contractId);
            exit;
        }

        $code = (string)($_GET['code'] ?? '');
        if ($code === '') {
            http_response_code(400);
            exit('Missing authorization code.');
        }

        // Exchange code for token
        $cfg = $this->config();
        $ch = curl_init($cfg['auth_server'] . '/oauth/token');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query([
                'grant_type'   => 'authorization_code',
                'code'         => $code,
                'redirect_uri' => $cfg['redirect_uri'],
            ]),
            CURLOPT_HTTPHEADER     => [
                'Authorization: Basic ' . base64_encode($cfg['client_id'] . ':' . $cfg['client_secret']),
                'Content-Type: application/x-www-form-urlencoded',
            ],
            CURLOPT_TIMEOUT        => 20,
        ]);
        $resp     = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            http_response_code(502);
            exit('DocuSign token exchange failed (HTTP ' . $httpCode . ').');
        }

        $tokenData = json_decode((string)$resp, true);
        if (empty($tokenData['access_token'])) {
            http_response_code(502);
            exit('DocuSign token exchange returned an invalid response.');
        }

        $this->storeToken($tokenData);

        if (!$this->fetchAndStoreUserInfo($tokenData['access_token'])) {
            http_response_code(502);
            exit('Could not retrieve DocuSign account information.');
        }

        header('Location: /index.php?page=docusign_send');
        exit;
    }

    // -------------------------------------------------------------------------
    // Action: Show signer form
    // GET  /index.php?page=docusign_send
    // -------------------------------------------------------------------------

    public function showSendForm(): void
    {
        $docId      = (int)($_SESSION['ds_pending_doc_id']      ?? 0);
        $contractId = (int)($_SESSION['ds_pending_contract_id'] ?? 0);

        if ($docId <= 0) {
            header('Location: /index.php?page=contracts');
            exit;
        }

        $token = $this->getToken();
        if ($token === null) {
            // Token gone — restart OAuth
            header('Location: /index.php?page=docusign_auth&doc_id=' . $docId . '&contract_id=' . $contractId);
            exit;
        }

        $doc = $this->loadDocument($docId);
        if ($doc === false) {
            http_response_code(404);
            exit('Document not found.');
        }

        // ── Build suggested town signers ──────────────────────────────────
        // Deduplicate by email to avoid showing the same person twice
        $seen          = [];
        $townSigners   = [];

        $addSigner = function(string $name, ?string $email, string $role) use (&$townSigners, &$seen): void {
            $name  = trim($name);
            $email = trim((string)$email);
            if ($name === '' || $email === '' || isset($seen[$email])) return;
            $seen[$email] = true;
            $townSigners[] = ['name' => $name, 'email' => $email, 'role' => $role];
        };

        // 1. Town Manager(s)
        foreach ($this->getPeopleByRoleKey('TOWN_MANAGER') as $p) {
            $addSigner($p['name'], $p['email'], 'Town Manager');
        }
        // 2. Finance Director(s)
        foreach ($this->getPeopleByRoleKey('FINANCE_DIRECTOR') as $p) {
            $addSigner($p['name'], $p['email'], 'Finance Director');
        }
        // 3. Town Attorney (TOWN_ATTORNEY role only)
        foreach ($this->getPeopleByRoleKey('TOWN_ATTORNEY') as $p) {
            $addSigner($p['name'], $p['email'], 'Town Attorney');
        }
        // 4. Town Clerk
        foreach ($this->getPeopleByRoleKey('TOWN_CLERK') as $p) {
            $addSigner($p['name'], $p['email'], 'Town Clerk');
        }
        // 5. Department Head for this contract's department
        if (!empty($doc['dept_head_name']) && !empty($doc['dept_head_email'])) {
            $addSigner($doc['dept_head_name'], $doc['dept_head_email'], 'Department Head');
        }
        // 6. Town contact (owner_primary_contact) on the contract
        if (!empty($doc['owner_name']) && !empty($doc['owner_email'])) {
            $addSigner($doc['owner_name'], $doc['owner_email'], 'Town Contact');
        }

        require APP_ROOT . '/app/views/docusign/send.php';
    }

    // -------------------------------------------------------------------------
    // Action: Create and send envelope
    // POST /index.php?page=docusign_send_envelope
    // -------------------------------------------------------------------------

    public function sendEnvelope(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            exit;
        }

        $docId      = (int)($_SESSION['ds_pending_doc_id']      ?? 0);
        $contractId = (int)($_SESSION['ds_pending_contract_id'] ?? 0);
        $token      = $this->getToken();
        $accountId  = (string)($_SESSION['ds_account_id'] ?? '');
        $baseUri    = (string)($_SESSION['ds_base_uri']   ?? '');

        if ($docId <= 0 || $token === null || $accountId === '' || $baseUri === '') {
            $_SESSION['docusign_flash_error'] = 'DocuSign session data is missing. Please try again.';
            header('Location: /index.php?page=contracts_show&contract_id=' . $contractId);
            exit;
        }

        // --- Validate signers ---
        $signerNames     = (array)($_POST['signer_name']    ?? []);
        $signerEmails    = (array)($_POST['signer_email']   ?? []);
        $signerCompanies = (array)($_POST['signer_company'] ?? []);

        if (count($signerNames) === 0) {
            $_SESSION['docusign_flash_error'] = 'At least one signer is required.';
            header('Location: /index.php?page=docusign_send');
            exit;
        }

        $signers  = [];
        $maxCount = min(count($signerNames), count($signerEmails), 10);
        for ($i = 0; $i < $maxCount; $i++) {
            $name    = trim((string)$signerNames[$i]);
            $email   = trim((string)$signerEmails[$i]);
            $company = trim((string)($signerCompanies[$i] ?? ''));
            if ($name === '' || $email === '') {
                continue;
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $_SESSION['docusign_flash_error'] = 'Invalid email address: ' . htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
                header('Location: /index.php?page=docusign_send');
                exit;
            }
            $routingOrder = (string)($i + 1);
            // Helper to build an anchor tab definition
            $anchorTab = fn(string $anchor) => [
                'anchorString'             => $anchor,
                'anchorIgnoreIfNotPresent' => 'true',
                'anchorUnits'              => 'pixels',
                'anchorXOffset'            => '0',
                'anchorYOffset'            => '0',
            ];

            $signerDef = [
                'email'        => $email,
                'name'         => $name,
                'recipientId'  => $routingOrder,
                'routingOrder' => $routingOrder,
            ];
            if ($company !== '') {
                $signerDef['company'] = $company;
            }
            $signerDef['tabs'] = [
                    // Place with **signature_1**, **signature_2**, etc.
                    'signHereTabs'    => [$anchorTab('**signature_' . $routingOrder . '**')],
                    // Place with **full_name_1**, **full_name_2**, etc.
                    // Pre-filled with the signer's name (read-only).
                    'fullNameTabs'    => [$anchorTab('**full_name_' . $routingOrder . '**')],
                    // Place with **company_name_1**, **company_name_2**, etc.
                    // Pre-filled with the company name entered on the send form.
                    'companyTabs'     => [$anchorTab('**company_name_' . $routingOrder . '**')],
                    // Place with **title_1**, **title_2**, etc.
                    // Editable title/position field — signer can confirm or fill in.
                    'titleTabs'       => [$anchorTab('**title_' . $routingOrder . '**')],
                    // Place with **date_signed_1**, **date_signed_2**, etc.
                    // Auto-filled with the date the signer signs.
                    'dateSignedTabs'  => [$anchorTab('**date_signed_' . $routingOrder . '**')],
            ];
            $signers[] = $signerDef;
        }

        if (count($signers) === 0) {
            $_SESSION['docusign_flash_error'] = 'At least one complete signer (name and email) is required.';
            header('Location: /index.php?page=docusign_send');
            exit;
        }

        // --- Load document file ---
        $doc = $this->loadDocument($docId);
        if ($doc === false) {
            http_response_code(404);
            exit('Document not found.');
        }

        $filePath = (string)$doc['file_path'];
        // Resolve relative paths to an absolute disk path
        if (!str_starts_with($filePath, '/')) {
            $filePath = rtrim((string)APP_ROOT, '/') . '/' . ltrim($filePath, '/');
        }
        if (!is_file($filePath)) {
            $_SESSION['docusign_flash_error'] = 'Document file not found on disk. Looking for: ' . htmlspecialchars($filePath, ENT_QUOTES, 'UTF-8');
            header('Location: /index.php?page=docusign_send');
            exit;
        }

        $fileContent = file_get_contents($filePath);
        if ($fileContent === false) {
            $_SESSION['docusign_flash_error'] = 'Could not read document file.';
            header('Location: /index.php?page=docusign_send');
            exit;
        }

        $ext     = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $mimeMap = [
            'pdf'  => 'application/pdf',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'doc'  => 'application/msword',
        ];
        $fileExt  = $ext ?: 'pdf';
        $mimeType = $mimeMap[$ext] ?? (string)($doc['mime_type'] ?? 'application/octet-stream');

        $emailSubject = trim((string)($_POST['email_subject'] ?? ''));
        if ($emailSubject === '') {
            $emailSubject = 'Please sign: ' . ((string)$doc['file_name'] ?: 'Contract Document');
        }

        $envelope = [
            'emailSubject' => $emailSubject,
            'documents'    => [[
                'documentBase64' => base64_encode($fileContent),
                'name'           => (string)($doc['file_name'] ?: 'document.' . $fileExt),
                'fileExtension'  => $fileExt,
                'documentId'     => '1',
            ]],
            'recipients'   => ['signers' => $signers],
            'status'       => 'sent',
        ];

        // --- Send to DocuSign API ---
        $ch = curl_init($baseUri . '/restapi/v2.1/accounts/' . rawurlencode($accountId) . '/envelopes');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($envelope),
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_TIMEOUT        => 45,
        ]);
        $resp     = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 201) {
            $errData = json_decode((string)$resp, true);
            $errMsg  = (string)($errData['message'] ?? ('DocuSign API error (HTTP ' . $httpCode . ')'));
            $_SESSION['docusign_flash_error'] = htmlspecialchars($errMsg, ENT_QUOTES, 'UTF-8');
            header('Location: /index.php?page=docusign_send');
            exit;
        }

        $result     = json_decode((string)$resp, true);
        $envelopeId = (string)($result['envelopeId'] ?? '');

        if ($envelopeId !== '') {
            $upd = $this->db->prepare("
                UPDATE contract_documents
                SET    docusign_envelope_id = :env_id,
                       docusign_status      = 'sent',
                       docusign_sent_at     = NOW()
                WHERE  contract_document_id = :doc_id
            ");
            $upd->execute([':env_id' => $envelopeId, ':doc_id' => $docId]);

            $person   = current_person();
            $personId = (int)($person['person_id'] ?? 0) ?: null;

            // Fetch old status name before updating
            $oldStatusRow = $this->db->prepare(
                "SELECT cs.contract_status_name
                 FROM contracts c
                 LEFT JOIN contract_statuses cs ON cs.contract_status_id = c.contract_status_id
                 WHERE c.contract_id = ? LIMIT 1"
            );
            $oldStatusRow->execute([$contractId]);
            $oldStatusName = (string)($oldStatusRow->fetchColumn() ?? '');

            // Update contract status to "Out For Signature" (id=7)
            $this->db->prepare(
                "UPDATE contracts SET contract_status_id = 7 WHERE contract_id = ? AND contract_status_id != 7"
            )->execute([$contractId]);

            $this->db->prepare(
                "INSERT INTO contract_status_history (contract_id, event_type, old_status, new_status, changed_by, changed_at, notes)
                 VALUES (?, 'docusign', ?, 'Out For Signature', ?, NOW(), ?)"
            )->execute([$contractId, $oldStatusName ?: null, $personId, 'Sent for signature via DocuSign (envelope ' . $envelopeId . ')']);
        }

        unset($_SESSION['ds_pending_doc_id'], $_SESSION['ds_pending_contract_id']);
        $_SESSION['docusign_flash_success'] = 'Envelope sent for signature successfully.';
        header('Location: /index.php?page=contracts_show&contract_id=' . $contractId);
        exit;
    }

    // -------------------------------------------------------------------------
    // Action: Void an envelope
    // POST /index.php?page=docusign_void
    // -------------------------------------------------------------------------

    public function voidEnvelope(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            exit;
        }

        $docId      = (int)($_POST['doc_id']      ?? 0);
        $contractId = (int)($_POST['contract_id'] ?? 0);
        $token      = $this->getToken();
        $accountId  = (string)($_SESSION['ds_account_id'] ?? '');
        $baseUri    = (string)($_SESSION['ds_base_uri']   ?? '');

        if ($docId <= 0 || $token === null || $accountId === '' || $baseUri === '') {
            $_SESSION['docusign_flash_error'] = 'DocuSign session expired. Please re-authenticate by sending again.';
            header('Location: /index.php?page=contracts_show&contract_id=' . $contractId);
            exit;
        }

        $stmt = $this->db->prepare("
            SELECT docusign_envelope_id FROM contract_documents WHERE contract_document_id = :id
        ");
        $stmt->execute([':id' => $docId]);
        $row        = $stmt->fetch();
        $envelopeId = (string)($row['docusign_envelope_id'] ?? '');

        if ($envelopeId === '') {
            $_SESSION['docusign_flash_error'] = 'No envelope found for this document.';
            header('Location: /index.php?page=contracts_show&contract_id=' . $contractId);
            exit;
        }

        $voidReason = trim((string)($_POST['void_reason'] ?? 'Voided by sender'));
        if ($voidReason === '') {
            $voidReason = 'Voided by sender';
        }

        $ch = curl_init($baseUri . '/restapi/v2.1/accounts/' . rawurlencode($accountId) . '/envelopes/' . rawurlencode($envelopeId));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => 'PUT',
            CURLOPT_POSTFIELDS     => json_encode(['status' => 'voided', 'voidedReason' => $voidReason]),
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_TIMEOUT        => 20,
        ]);
        $resp     = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            $upd = $this->db->prepare("
                UPDATE contract_documents SET docusign_status = 'voided' WHERE contract_document_id = :id
            ");
            $upd->execute([':id' => $docId]);

            $person   = current_person();
            $personId = (int)($person['person_id'] ?? 0) ?: null;
            $this->db->prepare(
                "INSERT INTO contract_status_history (contract_id, event_type, old_status, new_status, changed_by, changed_at, notes)
                 VALUES (?, 'docusign', 'sent', 'voided', ?, NOW(), ?)"
            )->execute([$contractId, $personId, 'DocuSign envelope voided. Reason: ' . $voidReason]);

            $_SESSION['docusign_flash_success'] = 'Envelope voided successfully.';
        } else {
            $errData = json_decode((string)$resp, true);
            $errMsg  = (string)($errData['message'] ?? ('Void failed (HTTP ' . $httpCode . ')'));
            $_SESSION['docusign_flash_error'] = htmlspecialchars($errMsg, ENT_QUOTES, 'UTF-8');
        }

        header('Location: /index.php?page=contracts_show&contract_id=' . $contractId);
        exit;
    }
}
