<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('user');

$user = getCurrentUser($pdo);

// Biletleri al
$stmt = $pdo->prepare("SELECT t.*, tr.*, bc.name as company_name,
                       tr.origin_city, tr.destination_city, tr.departure_time, tr.arrival_time, tr.price
                       FROM Tickets t
                       JOIN Trips tr ON t.trip_id = tr.id
                       JOIN Bus_Company bc ON tr.company_id = bc.id
                       WHERE t.user_id = ?
                       ORDER BY t.created_at DESC");
$stmt->execute([$user['id']]);
$tickets = $stmt->fetchAll();

$page_title = "Biletlerim";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="user-panel">
    <h1>Biletlerim</h1>
    
    <div class="user-info-card">
        <h3>👤 <?php echo clean($user['full_name']); ?></h3>
        <p>📧 <?php echo clean($user['email']); ?></p>
        <p>💳 Bakiye: <strong><?php echo formatMoney($user['balance']); ?></strong></p>
    </div>
    
    <?php if (empty($tickets)): ?>
        <div class="no-tickets">
            <p>Henüz hiç biletiniz yok.</p>
            <a href="/index.php" class="btn btn-primary">Bilet Ara</a>
        </div>
    <?php else: ?>
        <div class="tickets-list">
            <?php foreach ($tickets as $ticket): ?>
                <?php
                $is_past = strtotime($ticket['departure_time']) < time();
                $can_cancel = getHoursDifference($ticket['departure_time'], date('Y-m-d H:i:s')) > 1;
                $is_cancelled = $ticket['status'] === 'cancelled';
                ?>
                <div class="ticket-card <?php echo $is_cancelled ? 'cancelled' : ''; ?>">
                    <div class="ticket-header">
                        <h3>Bilet #<?php echo $ticket['id']; ?></h3>
                        <span class="ticket-status <?php echo $ticket['status']; ?>">
                            <?php echo $is_cancelled ? '❌ İptal Edildi' : ($is_past ? '✅ Tamamlandı' : '🎫 Aktif'); ?>
                        </span>
                    </div>
                    
                    <div class="ticket-body">
                        <div class="ticket-info-row">
                            <strong>Firma:</strong>
                            <span><?php echo clean($ticket['company_name']); ?></span>
                        </div>
                        
                        <div class="ticket-route">
                            <div class="ticket-city">
                                <strong><?php echo clean($ticket['origin_city']); ?></strong>
                                <span><?php echo date('d.m.Y', strtotime($ticket['departure_time'])); ?></span>
                                <span><?php echo date('H:i', strtotime($ticket['departure_time'])); ?></span>
                            </div>
                            <div class="ticket-arrow">→</div>
                            <div class="ticket-city">
                                <strong><?php echo clean($ticket['destination_city']); ?></strong>
                                <span><?php echo date('d.m.Y', strtotime($ticket['arrival_time'])); ?></span>
                                <span><?php echo date('H:i', strtotime($ticket['arrival_time'])); ?></span>
                            </div>
                        </div>
                        
                        <div class="ticket-details">
                            <span>💺 Koltuk: <strong><?php echo $ticket['seat_number']; ?></strong></span>
                            <span>💰 Ücret: <strong><?php echo formatMoney($ticket['total_price']); ?></strong></span>
                        </div>
                        
                        <div class="ticket-date">
                            <small>Alım Tarihi: <?php echo formatDate($ticket['created_at']); ?></small>
                        </div>
                    </div>
                    
                    <div class="ticket-actions">
                        <a href="/user/download-ticket.php?id=<?php echo $ticket['id']; ?>" 
                           class="btn btn-secondary" target="_blank">
                            📄 PDF İndir
                        </a>
                        
                        <?php if (!$is_cancelled && !$is_past && $can_cancel): ?>
                            <a href="/user/cancel-ticket.php?id=<?php echo $ticket['id']; ?>" 
                               class="btn btn-danger"
                               onclick="return confirm('Bu bileti iptal etmek istediğinizden emin misiniz? Bilet ücreti hesabınıza iade edilecektir.')">
                                ❌ İptal Et
                            </a>
                        <?php elseif (!$is_cancelled && !$is_past && !$can_cancel): ?>
                            <button class="btn btn-disabled" disabled>
                                ⏱️ İptal Süresi Geçti
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<style>
.user-panel {
    max-width: 1000px;
    margin: 0 auto;
}

.user-info-card {
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
    color: white;
    padding: 2rem;
    border-radius: 10px;
    margin-bottom: 2rem;
}

.no-tickets {
    text-align: center;
    padding: 3rem;
    background: white;
    border-radius: 10px;
}

.tickets-list {
    display: grid;
    gap: 1.5rem;
}

.ticket-card {
    background: white;
    border-radius: 10px;
    padding: 1.5rem;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.ticket-card.cancelled {
    opacity: 0.6;
    border: 2px solid #fca5a5;
}

.ticket-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--border-color);
}

.ticket-status {
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-size: 0.9rem;
    font-weight: bold;
}

.ticket-status.active {
    background: #d1fae5;
    color: #065f46;
}

.ticket-status.cancelled {
    background: #fee2e2;
    color: #991b1b;
}

.ticket-info-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 1rem;
}

.ticket-route {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin: 1.5rem 0;
    padding: 1rem;
    background: var(--bg-light);
    border-radius: 5px;
}

.ticket-city {
    flex: 1;
    text-align: center;
}

.ticket-city strong {
    display: block;
    font-size: 1.2rem;
    margin-bottom: 0.25rem;
}

.ticket-city span {
    display: block;
    font-size: 0.9rem;
    color: var(--text-light);
}

.ticket-arrow {
    font-size: 2rem;
    color: var(--primary-color);
    margin: 0 1rem;
}

.ticket-details {
    display: flex;
    gap: 2rem;
    margin: 1rem 0;
}

.ticket-date {
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px solid var(--border-color);
    color: var(--text-light);
}

.ticket-actions {
    display: flex;
    gap: 1rem;
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px solid var(--border-color);
}

.btn-danger {
    background: #ef4444;
    color: white;
}

.btn-danger:hover {
    background: #dc2626;
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>