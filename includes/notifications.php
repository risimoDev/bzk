<?php
$notifications = $pdo->query("SELECT * FROM notifications WHERE active = 1 ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
if ($notifications):
?>
<div id="site-notifications" class="fixed bottom-4 left-4 right-4 space-y-3 z-50">
  <?php foreach ($notifications as $n): ?>
    <div data-id="<?= $n['id'] ?>" 
         class="notification p-4 rounded-lg shadow flex justify-between items-center
         <?= $n['type']=='success'?'bg-green-600':
            ($n['type']=='warning'?'bg-yellow-600':
            ($n['type']=='error'?'bg-red-600':'bg-blue-600')) ?> text-white">
      <div>
        <h4 class="font-bold"><?= htmlspecialchars($n['title']) ?></h4>
        <p><?= htmlspecialchars($n['message']) ?></p>
      </div>
      <button class="ml-4 text-white font-bold close-btn">✖</button>
    </div>
  <?php endforeach; ?>
</div>
<script>
document.querySelectorAll('.notification').forEach(el => {
  const id = el.dataset.id;
  if (localStorage.getItem('notification_' + id) === 'closed') {
    el.style.display = 'none';
  }
  el.querySelector('.close-btn').addEventListener('click', () => {
    el.style.display = 'none';
    localStorage.setItem('notification_' + id, 'closed');
  });
});
</script>
<?php endif; ?>
