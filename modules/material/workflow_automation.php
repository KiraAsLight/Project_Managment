<?php
/**
 * Workflow Automation - Automatic status updates between divisions
 */

require_once '../../config/database.php';
require_once '../../config/config.php';
require_once '../../includes/functions.php';

class WorkflowAutomation
{
    private $conn;

    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    /**
     * Check and update workflow status when Engineering completes material upload
     */
    public function checkEngineeringCompletion($task_id)
    {
        $query = "SELECT COUNT(*) as total_materials 
                  FROM material_lists 
                  WHERE task_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $task_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();

        if ($data['total_materials'] > 0) {
            // Update Engineering progress to completed
            $this->updateDivisionProgress($task_id, 'Engineering', 'Completed', 100);

            // Auto-create Purchasing tasks if not exists
            $this->createNextDivisionTasks($task_id, 'Purchasing');

            return true;
        }

        return false;
    }

    /**
     * Check and update when Purchasing completes procurement
     */
    public function checkPurchasingCompletion($task_id)
    {
        $query = "SELECT COUNT(*) as total, 
                         SUM(CASE WHEN mp.status = 'Completed' THEN 1 ELSE 0 END) as completed
                  FROM material_progress mp
                  JOIN material_lists ml ON mp.material_id = ml.material_id
                  WHERE ml.task_id = ? AND mp.division = 'Purchasing'";

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $task_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();

        if ($data['total'] > 0 && $data['completed'] == $data['total']) {
            // Update Purchasing progress to completed
            $this->updateDivisionProgress($task_id, 'Purchasing', 'Completed', 100);

            // Auto-create Fabrikasi tasks
            $this->createNextDivisionTasks($task_id, 'Fabrikasi');

            return true;
        }

        return false;
    }

    /**
     * Check and update when Fabrikasi completes production
     */
    public function checkFabrikasiCompletion($task_id)
    {
        $query = "SELECT COUNT(*) as total, 
                         SUM(CASE WHEN mp.status = 'Completed' THEN 1 ELSE 0 END) as completed
                  FROM material_progress mp
                  JOIN material_lists ml ON mp.material_id = ml.material_id
                  WHERE ml.task_id = ? AND mp.division = 'Fabrikasi'";

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $task_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();

        if ($data['total'] > 0 && $data['completed'] == $data['total']) {
            // Update Fabrikasi progress to completed
            $this->updateDivisionProgress($task_id, 'Fabrikasi', 'Completed', 100);

            // Auto-create Logistik tasks
            $this->createNextDivisionTasks($task_id, 'Logistik');

            return true;
        }

        return false;
    }

    /**
     * Check and update when Logistik completes delivery
     */
    public function checkLogistikCompletion($task_id)
    {
        $query = "SELECT COUNT(*) as total, 
                         SUM(CASE WHEN mp.status = 'Completed' THEN 1 ELSE 0 END) as completed
                  FROM material_progress mp
                  JOIN material_lists ml ON mp.material_id = ml.material_id
                  WHERE ml.task_id = ? AND mp.division = 'Logistik'";

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $task_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();

        if ($data['total'] > 0 && $data['completed'] == $data['total']) {
            // Update Logistik progress to completed
            $this->updateDivisionProgress($task_id, 'Logistik', 'Completed', 100);

            // Mark overall task as completed if all divisions are done
            $this->checkTaskCompletion($task_id);

            return true;
        }

        return false;
    }

    /**
     * Update division progress for a task
     */
    private function updateDivisionProgress($task_id, $division, $status, $progress)
    {
        $query = "UPDATE tasks 
                  SET status = ?, progress = ?
                  WHERE task_id = ? AND responsible_division = ?";

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("sdis", $status, $progress, $task_id, $division);
        $stmt->execute();

        // Log the activity
        log_activity(
            $this->conn,
            $_SESSION['user_id'] ?? 1,
            'Workflow Update',
            "Division {$division} completed for task ID: {$task_id}"
        );
    }

    /**
     * Create tasks for next division in workflow
     */
    private function createNextDivisionTasks($task_id, $next_division)
    {
        // Get base task info
        $query = "SELECT pon_id, phase, task_name, start_date, finish_date 
                  FROM tasks WHERE task_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $task_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $base_task = $result->fetch_assoc();

        if (!$base_task) return;

        // Check if next division task already exists
        $check_query = "SELECT COUNT(*) as exists_count 
                        FROM tasks 
                        WHERE pon_id = ? AND responsible_division = ?";
        $check_stmt = $this->conn->prepare($check_query);
        $check_stmt->bind_param("is", $base_task['pon_id'], $next_division);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        $exists_data = $check_result->fetch_assoc();

        if ($exists_data['exists_count'] == 0) {
            // Create new task for next division
            $task_name_map = [
                'Purchasing' => 'Procurement Management',
                'Fabrikasi' => 'Production & Fabrication',
                'Logistik' => 'Delivery & Logistics',
                'QC' => 'Quality Control Inspection'
            ];

            $insert_query = "INSERT INTO tasks 
                            (pon_id, phase, responsible_division, task_name, start_date, finish_date, status, progress)
                            VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 1 DAY), DATE_ADD(NOW(), INTERVAL 7 DAY), 'Not Started', 0)";

            $insert_stmt = $this->conn->prepare($insert_query);
            $insert_stmt->bind_param(
                "isss",
                $base_task['pon_id'],
                $base_task['phase'],
                $next_division,
                $task_name_map[$next_division] ?? $next_division . ' Task'
            );
            $insert_stmt->execute();
        }
    }

    /**
     * Check if entire task is completed
     */
    private function checkTaskCompletion($task_id)
    {
        $query = "SELECT COUNT(*) as total_divisions,
                         SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed_divisions
                  FROM tasks 
                  WHERE pon_id = (SELECT pon_id FROM tasks WHERE task_id = ?)";

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $task_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();

        if ($data['total_divisions'] > 0 && $data['completed_divisions'] == $data['total_divisions']) {
            // Update PON overall progress
            $this->updatePONProgress($task_id);
        }
    }

    /**
     * Update PON overall progress
     */
    private function updatePONProgress($task_id)
    {
        $query = "SELECT pon_id FROM tasks WHERE task_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $task_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $task_data = $result->fetch_assoc();

        if ($task_data) {
            $pon_id = $task_data['pon_id'];

            // Calculate average progress across all tasks
            $progress_query = "SELECT AVG(progress) as avg_progress 
                              FROM tasks 
                              WHERE pon_id = ?";
            $progress_stmt = $this->conn->prepare($progress_query);
            $progress_stmt->bind_param("i", $pon_id);
            $progress_stmt->execute();
            $progress_result = $progress_stmt->get_result();
            $progress_data = $progress_result->fetch_assoc();

            $overall_progress = round($progress_data['avg_progress'] ?? 0, 2);

            // Update PON
            $update_query = "UPDATE pon SET overall_progress = ? WHERE pon_id = ?";
            $update_stmt = $this->conn->prepare($update_query);
            $update_stmt->bind_param("di", $overall_progress, $pon_id);
            $update_stmt->execute();
        }
    }
}

// Usage example:
// $workflow = new WorkflowAutomation($conn);
// $workflow->checkEngineeringCompletion($task_id);
?>