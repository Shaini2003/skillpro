<?php
// events.php
require_once 'config.php';
$db = Database::getInstance()->getConnection();

$stmt = $db->query("SELECT * FROM events WHERE event_date >= CURDATE() ORDER BY event_date ASC");
$events = $stmt->fetchAll();

include 'includes/header.php';
?>

<div class="container mt-5 mb-5">
    <h2 class="mb-4">Upcoming Events</h2>
    
    <div class="timeline">
        <?php if (empty($events)): ?>
            <div class="alert alert-info">No upcoming events scheduled.</div>
        <?php else: ?>
            <?php foreach ($events as $event): ?>
            <div class="card mb-3 shadow-sm">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-2 text-center border-end">
                            <h2 class="text-success fw-bold display-5 mb-0"><?= date('d', strtotime($event['event_date'])) ?></h2>
                            <span class="text-uppercase text-muted"><?= date('M Y', strtotime($event['event_date'])) ?></span>
                        </div>
                        <div class="col-md-8">
                            <h5 class="card-title text-primary"><?= htmlspecialchars($event['event_title']) ?></h5>
                            <p class="mb-1"><i class="bi bi-clock"></i> <?= date('g:i A', strtotime($event['start_time'])) ?> - <?= date('g:i A', strtotime($event['end_time'])) ?></p>
                            <p class="mb-1"><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($event['location']) ?></p>
                            <p class="card-text text-muted"><?= htmlspecialchars($event['description']) ?></p>
                        </div>
                        <div class="col-md-2 text-center">
                            <span class="badge bg-light text-dark border"><?= ucfirst(str_replace('_', ' ', $event['event_type'])) ?></span>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>