<?php
declare(strict_types=1);

class ProcurementGateController
{
    public function index(): void
    {
        $pageTitle = 'Procurement Decision Tool';
        $gateConfig = [
            'requisition_url' => '/finance/requisition.php',
            'procurement_url' => '/procurement/intake.php',
            'contracts_url'   => 'index.php?page=contracts_create',
        ];

        $GLOBALS['pageTitle'] = $pageTitle;
        $GLOBALS['gateConfig'] = $gateConfig;

        require APP_ROOT . '/app/views/procurement_gate/index.php';
    }

    public function evaluate(): void
    {
        header('Content-Type: application/json');

        $input = json_decode(file_get_contents('php://input'), true);

        $goodsOnly          = !empty($input['goods_only']);
        $under30k           = !empty($input['under_30000']);
        $installOnProperty  = !empty($input['install_on_property']);
        $constructionRepair = !empty($input['construction_repair']);
        $formalInformalBid  = !empty($input['bidding_required']);
        $professionalSvc    = !empty($input['professional_services']);
        $bidCompleted       = !empty($input['bid_completed']);

        $result = $this->determineOutcome(
            $goodsOnly,
            $under30k,
            $installOnProperty,
            $constructionRepair,
            $formalInformalBid,
            $professionalSvc,
            $bidCompleted
        );

        echo json_encode($result);
        exit;
    }

    private function determineOutcome(
        bool $goodsOnly,
        bool $under30k,
        bool $installOnProperty,
        bool $constructionRepair,
        bool $biddingRequired,
        bool $professionalServices,
        bool $bidCompleted
    ): array {
        if ($professionalServices) {
            if ($bidCompleted) {
                return [
                    'status' => 'proceed_to_contract',
                    'message' => 'Procurement/QBS process appears complete. You may proceed to the Contracts Database.',
                    'next_url' => 'index.php?page=contracts_create',
                ];
            }

            return [
                'status' => 'procurement_review',
                'message' => 'This appears to be a professional services procurement and should be routed through Procurement first.',
                'next_url' => '/procurement/intake.php',
            ];
        }

        if ($goodsOnly) {
            if ($under30k && !$installOnProperty) {
                return [
                    'status' => 'requisition_only',
                    'message' => 'This appears to be a simple goods/supplies purchase under $30,000 with no installation or work on Town property. Use a requisition and PO instead of the Contracts Database.',
                    'next_url' => '/finance/requisition.php',
                ];
            }

            if ($biddingRequired && !$bidCompleted) {
                return [
                    'status' => 'procurement_review',
                    'message' => 'Bidding appears to be required. Complete the procurement process first, then return to the Contracts Database if a contract is needed.',
                    'next_url' => '/procurement/intake.php',
                ];
            }

            return [
                'status' => 'proceed_to_contract',
                'message' => 'You may proceed to the Contracts Database.',
                'next_url' => 'index.php?page=contracts_create',
            ];
        }

        if ($constructionRepair) {
            if ($biddingRequired && !$bidCompleted) {
                return [
                    'status' => 'procurement_review',
                    'message' => 'Construction/repair work requiring bidding must go through Procurement first.',
                    'next_url' => '/procurement/intake.php',
                ];
            }

            return [
                'status' => 'proceed_to_contract',
                'message' => 'You may proceed to the Contracts Database.',
                'next_url' => 'index.php?page=contracts_create',
            ];
        }

        if ($biddingRequired && !$bidCompleted) {
            return [
                'status' => 'procurement_review',
                'message' => 'Complete the required procurement/bid process first.',
                'next_url' => '/procurement/intake.php',
            ];
        }

        return [
            'status' => 'proceed_to_contract',
            'message' => 'You may proceed to the Contracts Database.',
            'next_url' => 'index.php?page=contracts_create',
        ];
    }
}