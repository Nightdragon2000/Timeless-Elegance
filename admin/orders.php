<?php
$page_title = "Order Management";

require_once 'includes/header.php';

// Delete logic
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['delete_order'], $_POST['order_id'])
    && filter_var($_POST['order_id'], FILTER_VALIDATE_INT)
) {
    $delete_id = (int)$_POST['order_id'];

    // first delete items
    $sql1 = "DELETE FROM order_items WHERE order_id = ?";
    $st1  = mysqli_prepare($conn, $sql1);
    mysqli_stmt_bind_param($st1, "i", $delete_id);
    mysqli_stmt_execute($st1);
    mysqli_stmt_close($st1);

    // then delete order
    $sql2 = "DELETE FROM orders WHERE id = ?";
    $st2  = mysqli_prepare($conn, $sql2);
    mysqli_stmt_bind_param($st2, "i", $delete_id);
    if (mysqli_stmt_execute($st2)) {
        $success_message = "Order #{$delete_id} deleted successfully!";
    } else {
        $error_message = "Error deleting order.";
    }
    mysqli_stmt_close($st2);
}

// Get all orders 
$orders = [];
// Get items per page setting
$items_per_page = get_items_per_page($conn);

// Set up pagination
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($current_page - 1) * $items_per_page;

// Get total order count for pagination
$count_sql = "SELECT COUNT(*) as total FROM orders";
$count_result = mysqli_query($conn, $count_sql);
$count_row = mysqli_fetch_assoc($count_result);
$total_orders = $count_row['total'];
$total_pages = ceil($total_orders / $items_per_page);
mysqli_free_result($count_result);

// Get orders for current page
$sql = "SELECT o.*, o.total_amount as total, c.firstName, c.lastName 
            FROM orders o 
            JOIN customer c ON o.customer_id = c.id 
            ORDER BY o.id DESC
            LIMIT ?, ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ii", $offset, $items_per_page);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$orders = mysqli_fetch_all($result, MYSQLI_ASSOC);
mysqli_free_result($result);
mysqli_stmt_close($stmt);

// Count items in each order 
$count_sql = "SELECT COUNT(*) as count, SUM(quantity) as total_items FROM order_items WHERE order_id = ?";
$count_stmt = mysqli_prepare($conn, $count_sql);

foreach ($orders as $key => $order) {
    $order_id = $order['id'];
    mysqli_stmt_bind_param($count_stmt, "i", $order_id);
    mysqli_stmt_execute($count_stmt);
    $count_result = mysqli_stmt_get_result($count_stmt);
    $count_row = mysqli_fetch_assoc($count_result);
    $orders[$key]['item_count'] = $count_row['count'] ?? 0;
    $orders[$key]['total_items'] = $count_row['total_items'] ?? 0;
    mysqli_free_result($count_result);
}
mysqli_stmt_close($count_stmt);
?>

<!-- Content -->
<div>
    <?php if ($error_message): ?>
        <div class="alert alert-danger" role="alert">
            <?php echo htmlspecialchars($error_message); ?>
        </div>
    <?php endif; ?>

    <?php if ($success_message): ?>
        <div class="alert alert-success" role="alert">
            <?php echo htmlspecialchars($success_message); ?>
        </div>
    <?php endif; ?>
</div>

<div class="row mt-4">
    <div class="col-12">
        <div class="admin-card">
            <h2 class="admin-card-title">Order List</h2>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Date</th>
                            <th>Customer</th>
                            <th>Items</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($orders) > 0): ?>
                            <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td>#<?php echo htmlspecialchars($order['id']); ?></td>
                                    <td><?php echo date('j M Y', strtotime($order['order_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($order['firstName'] . ' ' . $order['lastName']); ?></td>
                                    <td><?php echo htmlspecialchars($order['total_items']); ?></td>
                                    <td>$<?php echo number_format($order['total'], 2); ?></td>
                                    <td>
                                        <div class="order-status status-<?php echo strtolower(htmlspecialchars($order['status'])); ?>">
                                            <?php echo htmlspecialchars($order['status']); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <a href="order_details.php?id=<?php echo htmlspecialchars($order['id']); ?>" class="edit-btn me-1">
                                            <i class="bi bi-eye me-1"></i> View
                                        </a>
                                        <form method="post" action="orders.php" class="delete-order-form" style="display:inline;">
                                            <input type="hidden" name="order_id" value="<?= htmlspecialchars($order['id']) ?>">
                                            <input type="hidden" name="delete_order" value="1">
                                            <button
                                                type="button"
                                                class="delete-btn me-1"
                                                data-order-id="<?= htmlspecialchars($order['id']) ?>">
                                                <i class="bi bi-trash me-1"></i> Delete
                                            </button>
                                        </form>

                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center">No orders found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Pagination Controls -->
<?php if ($total_pages > 1): ?>
    <div class="pagination-container mt-4">
        <?php echo generate_pagination($current_page, $total_pages, 'orders.php?'); ?>
    </div>
<?php endif; ?>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteOrderModal" tabindex="-1" aria-labelledby="deleteOrderLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteOrderLabel">Confirm Delete</h5>
            </div>
            <div class="modal-body">
                Are you sure you want to delete order
                <strong>#<span id="deleteOrderId"></span></strong>?
            </div>
            <div class="modal-footer">
                <button type="button" class="btn secondary-btn" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn primary-btn" id="confirmDeleteOrderBtn">Delete</button>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        // ----- Delete  Logic -----
        const modalEl = document.getElementById('deleteOrderModal');
        const deleteOrderModal = modalEl ? new bootstrap.Modal(modalEl) : null;
        const orderIdSpan = document.getElementById('deleteOrderId');
        const confirmDeleteBtn = document.getElementById('confirmDeleteOrderBtn');

        let formToDeleteOrder = null;

        document.querySelectorAll('.delete-btn').forEach(btn => {
            btn.addEventListener('click', e => {
                e.preventDefault();

                if (!deleteOrderModal || !orderIdSpan) return;
                formToDeleteOrder = btn.closest('form');
                orderIdSpan.textContent = btn.dataset.orderId || '';
                deleteOrderModal.show();
            });
        });

        if (confirmDeleteBtn) {
            confirmDeleteBtn.addEventListener('click', () => {
                if (formToDeleteOrder && deleteOrderModal) {
                    deleteOrderModal.hide();
                    formToDeleteOrder.submit();
                }
            });
        }
    });
</script>

<?php require_once 'includes/footer.php'; ?>