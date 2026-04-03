<?php
require_once '../dal/DashboardDAL.php';

class DashboardBLL {
    private $dashboardDAL;

    public function __construct($db) {
        $this->dashboardDAL = new DashboardDAL($db);
    }

    public function getDashboardSummary() {
        $revenueRow = $this->dashboardDAL->getTotalRevenue();
        $sessionRow = $this->dashboardDAL->countTodaySessions();
        $lowStock = $this->dashboardDAL->getLowStockProducts(20);
        $courts = $this->dashboardDAL->getCourtsStatus(7);
        $inventory = $this->dashboardDAL->getInventoryProducts();

        return [
            'total_revenue' => floatval($revenueRow['total_revenue'] ?? 0),
            'today_sessions' => intval($sessionRow['today_sessions'] ?? 0),
            'low_stock_count' => count($lowStock),
            'low_stock_products' => $lowStock,
            'courts' => $courts,
            'inventory' => $inventory,
        ];
    }
}
?>