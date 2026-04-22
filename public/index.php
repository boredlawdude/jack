<?php
ob_start();
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../app/bootstrap.php';
require_login();
require_once __DIR__ . '/../app/views/layouts/header.php';
require_once APP_ROOT . '/app/controllers/ContractsController.php';
require_once APP_ROOT . '/app/controllers/CompaniesController.php';
require_once APP_ROOT . '/app/controllers/PeopleController.php';
require_once APP_ROOT . '/app/controllers/ContractTypesController.php';
require_once APP_ROOT . '/app/controllers/AdminSettingsController.php';
require_once APP_ROOT . '/app/controllers/ContractStatusController.php';
require_once APP_ROOT . '/app/controllers/PaymentTermController.php';
require_once APP_ROOT . '/app/controllers/BiddingComplianceController.php';
require_once APP_ROOT . '/app/controllers/DocuSignController.php';
require_once APP_ROOT . '/app/controllers/DashboardController.php';
require_once APP_ROOT . '/app/controllers/RolesController.php';
require_once APP_ROOT . '/app/controllers/DevelopmentAgreementsController.php';
    require_once APP_ROOT . '/app/controllers/DevelopmentAgreementSubmissionsController.php';

$companiesController = new CompaniesController();
$PeopleController = new PeopleController();
$ContractsController = new ContractsController();
$ContractTypesController = new ContractTypesController();
$AdminSettingsController = new AdminSettingsController();
$ContractStatusController = new ContractStatusController();
$PaymentTermController = new PaymentTermController();
$BiddingComplianceController = new BiddingComplianceController();
$page = $_GET['page'] ?? 'dashboard';

switch ($page) {
    case 'procurement_gate':
        require_once APP_ROOT . '/app/controllers/ProcurementGateController.php';
        (new ProcurementGateController())->index();
        break;

    case 'procurement_gate_evaluate':
        require_once APP_ROOT . '/app/controllers/ProcurementGateController.php';
        (new ProcurementGateController())->evaluate();
        break;

    case 'dashboard':
        (new DashboardController())->index();
        break;
        

        case 'contract_documents_create':
        case 'contract_document_create':
            $contractId = (int)($_GET['contract_id'] ?? 0);
            require APP_ROOT . '/app/views/contract_documents/create.php';
            break;

        case 'contract_documents_store':
            $ContractsController->storeDocument();
            break;

        case 'contract_documents_save_order':
            $ContractsController->saveDocumentOrder();
            break;

    case 'bidding_compliance_store':
        $BiddingComplianceController->store();
        break;

    case 'bidding_compliance_delete':
        $BiddingComplianceController->delete();
        break;

    case 'contracts':
        $ContractsController->index();
        break;

    case 'contracts_show':
        $ContractsController->show((int)($_GET['contract_id'] ?? 0));
        break;

    case 'contracts_create':
        $ContractsController->create();
        break;

    case 'contracts_store':
        $ContractsController->store();
        break;

    case 'contracts_edit':
        
        require_once APP_ROOT . '/app/controllers/ContractsController.php';
        (new ContractsController())->edit((int)($_GET['contract_id'] ?? 0));
    break;
       

    case 'contracts_update':
        $ContractsController->update((int)($_GET['contract_id'] ?? 0));
        break;

    case 'contracts_delete':
        $ContractsController->destroy((int)($_GET['contract_id'] ?? 0));
        break;

    case 'contracts_bulk_delete':
        $ContractsController->bulkDestroy();
        break;

    case 'contracts_search':
        $ContractsController->search();
        break;

    case 'contracts_generate_print':
        $ContractsController->generateAndPrint((int)($_GET['contract_id'] ?? 0));
        break;

    case 'contracts_generate_html':
        $ContractsController->generateHtmlDocument((int)($_GET['contract_id'] ?? 0));
        break;

    case 'contracts_generate_word':
        $ContractsController->generateWordDocument((int)($_GET['contract_id'] ?? 0));
        break;

    case 'contract_types':
        $ContractTypesController->index();
        break;

    case 'contract_types_create':
        $ContractTypesController->create();
        break;

    case 'contract_types_store':
        $ContractTypesController->store();
        break;

    case 'contract_types_edit':
        $ContractTypesController->edit((int)($_GET['contract_type_id'] ?? 0));
        break;

    case 'contract_types_update':
        $ContractTypesController->update((int)($_GET['contract_type_id'] ?? 0));
        break;

    case 'contract_types_delete':
        $ContractTypesController->delete((int)($_GET['contract_type_id'] ?? 0));
        break;

    case 'approval_rules':
        require_once APP_ROOT . '/app/controllers/ApprovalRulesController.php';
        (new ApprovalRulesController())->index();
        break;

    case 'approval_rules_store':
        require_once APP_ROOT . '/app/controllers/ApprovalRulesController.php';
        (new ApprovalRulesController())->store();
        break;

    case 'approval_rules_update':
        require_once APP_ROOT . '/app/controllers/ApprovalRulesController.php';
        (new ApprovalRulesController())->update((int)($_GET['rule_id'] ?? 0));
        break;

    case 'approval_rules_delete':
        require_once APP_ROOT . '/app/controllers/ApprovalRulesController.php';
        (new ApprovalRulesController())->destroy((int)($_GET['rule_id'] ?? 0));
        break;

    case 'approval_stamp':
        require_once APP_ROOT . '/app/controllers/ApprovalRulesController.php';
        (new ApprovalRulesController())->stampApproval();
        break;

    case 'approval_email_risk_manager':
        require_once APP_ROOT . '/app/controllers/ApprovalRulesController.php';
        (new ApprovalRulesController())->emailRiskManager();
        break;

    case 'approval_email_risk_manager_reduced':
        require_once APP_ROOT . '/app/controllers/ApprovalRulesController.php';
        (new ApprovalRulesController())->emailRiskManagerReduced();
        break;

case 'companies':
    $companiesController->index();
    break;

case 'companies_show':
    $companiesController->show((int)($_GET['company_id'] ?? 0));
    break;

case 'companies_create':
    $companiesController->create();
    break;

case 'companies_store':
    $companiesController->store();
    break;

case 'companies_edit':
    $companiesController->edit((int)($_GET['company_id'] ?? 0));
    break;


case 'companies_update':
    $companiesController->update((int)($_GET['company_id'] ?? 0));
    break;

case 'companies_delete':
    $companiesController->destroy((int)($_GET['company_id'] ?? 0));
    break;

case 'companies_bulk_delete':
    $companiesController->bulkDestroy();
    break;

case 'companies_link_person':
    $companiesController->linkPerson((int)($_GET['company_id'] ?? 0));
    break;

case 'companies_unlink_person':
    $companiesController->unlinkPerson((int)($_GET['company_id'] ?? 0));
    break;

   case 'people':
    require_once APP_ROOT . '/app/controllers/PeopleController.php';
    (new PeopleController())->index();
    break;

case 'people_create':
    require_once APP_ROOT . '/app/controllers/PeopleController.php';
    (new PeopleController())->create();
    break;

case 'people_store':
    require_once APP_ROOT . '/app/controllers/PeopleController.php';
    (new PeopleController())->store();
    break;

case 'people_edit':
    require_once APP_ROOT . '/app/controllers/PeopleController.php';
    (new PeopleController())->edit();
    break;

case 'people_update':
    require_once APP_ROOT . '/app/controllers/PeopleController.php';
    (new PeopleController())->update();
    break;

case 'people_set_password':
    require_once APP_ROOT . '/app/controllers/PeopleController.php';
    (new PeopleController())->setPassword();
    break;

case 'departments':
    require_once APP_ROOT . '/app/controllers/DepartmentsController.php';
    (new DepartmentsController())->index();
    break;

case 'department_edit':
    require_once APP_ROOT . '/app/controllers/DepartmentsController.php';
    (new DepartmentsController())->edit();
    break;

case 'department_update':
    require_once APP_ROOT . '/app/controllers/DepartmentsController.php';
    (new DepartmentsController())->update();
    break;
case 'departments_create':
    require_once APP_ROOT . '/app/controllers/DepartmentsController.php';
    (new DepartmentsController())->create();
    break;

case 'departments_store':
    require_once APP_ROOT . '/app/controllers/DepartmentsController.php';
    (new DepartmentsController())->store();
    break;


    case 'contract_document_email':
        require_once __DIR__ . '/contract_document_email.php';
        break;

    case 'contract_document_compare':
        require_once __DIR__ . '/contract_document_compare.php';
        break;

    case 'contract_documents_merge_pdf':
        require_once __DIR__ . '/contract_documents_merge_pdf.php';
        break;

    case 'contract_history_add':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $ContractsController->addHistoryNote();
        } else {
            http_response_code(405);
            echo 'Method not allowed.';
        }
        break;

    case 'contract_history_delete':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $ContractsController->deleteHistoryNote();
        } else {
            http_response_code(405);
            echo 'Method not allowed.';
        }
        break;

    case 'contract_document_delete':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $ContractsController->deleteDocument();
        } else {
            http_response_code(405);
            echo 'Method not allowed.';
        }
        break;

    case 'admin_settings':
        require_once APP_ROOT . '/app/controllers/AdminSettingsController.php';
        (new AdminSettingsController())->index();
        break;

    case 'admin_settings_update':
        require_once APP_ROOT . '/app/controllers/AdminSettingsController.php';
        (new AdminSettingsController())->update();
        break;

    case 'admin_statuses':
        require_once APP_ROOT . '/app/controllers/ContractStatusController.php';
        (new ContractStatusController())->index();
        break;
    case 'admin_statuses_create':
        require_once APP_ROOT . '/app/controllers/ContractStatusController.php';
        (new ContractStatusController())->create();
        break;
    case 'admin_statuses_update':
        require_once APP_ROOT . '/app/controllers/ContractStatusController.php';
        (new ContractStatusController())->update();
        break;
    case 'admin_statuses_delete':
        require_once APP_ROOT . '/app/controllers/ContractStatusController.php';
        (new ContractStatusController())->delete();
        break;

    case 'admin_payment_terms':
        require_once APP_ROOT . '/app/controllers/PaymentTermController.php';
        (new PaymentTermController())->index();
        break;
    case 'admin_payment_terms_create':
        require_once APP_ROOT . '/app/controllers/PaymentTermController.php';
        (new PaymentTermController())->create();
        break;
    case 'admin_payment_terms_update':
        require_once APP_ROOT . '/app/controllers/PaymentTermController.php';
        (new PaymentTermController())->update();
        break;
    case 'admin_payment_terms_delete':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            require_once APP_ROOT . '/app/controllers/PaymentTermController.php';
            (new PaymentTermController())->delete();
        } else {
            http_response_code(405);
        }
        break;

    // ── User Roles ────────────────────────────────────────────────────────────

    case 'admin_roles':
        (new RolesController())->index();
        break;

    case 'admin_roles_create':
        (new RolesController())->create();
        break;

    case 'admin_roles_update':
        (new RolesController())->update();
        break;

    case 'admin_roles_delete':
        (new RolesController())->delete();
        break;

    // ── DocuSign ──────────────────────────────────────────────────────────────

    case 'docusign_auth':
        // Initiate OAuth flow (or skip to send form if already authenticated)
        (new DocuSignController())->initiateAuth();
        break;

    case 'docusign_callback':
        // OAuth redirect-back handler; exchanges code for token
        (new DocuSignController())->handleCallback();
        break;

    case 'docusign_send':
        // Renders the signer configuration form
        (new DocuSignController())->showSendForm();
        break;

    case 'docusign_send_envelope':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            (new DocuSignController())->sendEnvelope();
        } else {
            http_response_code(405);
        }
        break;

    case 'docusign_void':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            (new DocuSignController())->voidEnvelope();
        } else {
            http_response_code(405);
        }
        break;

    // ── Development Agreements ──────────────────────────────────────────
    case 'development_agreements':
        (new DevelopmentAgreementsController())->index();
        break;

    case 'development_agreements_create':
        (new DevelopmentAgreementsController())->create();
        break;

    case 'development_agreements_store':
        (new DevelopmentAgreementsController())->store();
        break;

    case 'development_agreements_show':
        (new DevelopmentAgreementsController())->show();
        break;

    case 'development_agreements_edit':
        (new DevelopmentAgreementsController())->edit();
        break;

    case 'development_agreements_update':
        (new DevelopmentAgreementsController())->update();
        break;

    case 'development_agreements_delete':
        (new DevelopmentAgreementsController())->delete();
        break;

    case 'dev_agreement_submissions':
        (new DevelopmentAgreementSubmissionsController())->index();
        break;

    case 'dev_agreement_submissions_show':
        (new DevelopmentAgreementSubmissionsController())->show();
        break;

    case 'dev_agreement_submissions_import':
        (new DevelopmentAgreementSubmissionsController())->import();
        break;

    case 'dev_agreement_submissions_reject':
        (new DevelopmentAgreementSubmissionsController())->reject();
        break;

    default:
        (new DashboardController())->index();
        break;
}
