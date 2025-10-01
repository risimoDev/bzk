<?php
$notifications = $pdo->query("SELECT * FROM notifications WHERE active = 1 ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
if ($notifications):
?>
<div id="site-notifications" class="fixed bottom-4 right-4 space-y-2 z-[90] w-64 sm:w-80 md:top-20 pt-16 md:pt-0 pointer-events-none">
  <?php foreach ($notifications as $n): ?>
    <div data-id="<?= $n['id'] ?>" 
         class="notification p-2 rounded-md shadow-lg flex justify-between items-start transition-all duration-300 transform hover:scale-[1.02] hover:shadow-xl
         <?= $n['type']=='success'?'bg-gradient-to-r from-green-500 to-green-600':
            ($n['type']=='warning'?'bg-gradient-to-r from-yellow-500 to-yellow-600':
            ($n['type']=='error'?'bg-gradient-to-r from-red-500 to-red-600':
            'bg-gradient-to-r from-blue-500 to-blue-600')) ?> text-white animate-slideIn max-w-xs pointer-events-auto">
      <div class="flex-1">
        <div class="flex items-center mb-1">
          <h4 class="font-bold text-sm mr-1"><?= htmlspecialchars($n['title']) ?></h4>
          <?php
          $icons = [
            'success' => 'fa-check-circle',
            'warning' => 'fa-exclamation-triangle',
            'error' => 'fa-times-circle',
            'info' => 'fa-info-circle'
          ];
          $icon = $icons[$n['type']] ?? 'fa-bell';
          ?>
          <i class="fas <?= $icon ?>"></i>
        </div>
        <p class="opacity-90 text-xs leading-relaxed"><?= htmlspecialchars($n['message']) ?></p>
        <div class="text-xs opacity-75 mt-3 flex items-center">
          <i class="far fa-clock mr-1"></i>
          <?= date('d.m.Y H:i', strtotime($n['created_at'])) ?>
        </div>
      </div>
      <button class="ml-2 text-white font-bold close-btn text-sm leading-none hover:bg-white hover:bg-opacity-20 rounded-full w-5 h-5 flex items-center justify-center transition-colors duration-300">✖</button>
    </div>
  <?php endforeach; ?>
</div>
<style>
  @keyframes slideIn {
    from {
      opacity: 0;
      transform: translateX(100%);
    }
    to {
      opacity: 1;
      transform: translateX(0);
    }
  }
  .animate-slideIn {
    animation: slideIn 0.3s ease-out forwards;
  }
</style>
<script>
document.querySelectorAll('.notification').forEach(el => {
  const id = el.dataset.id;
  if (localStorage.getItem('notification_' + id) === 'closed') {
    el.style.display = 'none';
  }
  el.querySelector('.close-btn').addEventListener('click', () => {
    el.style.opacity = '0';
    el.style.transform = 'translateX(100%)';
    setTimeout(() => {
      el.style.display = 'none';
      localStorage.setItem('notification_' + id, 'closed');
    }, 300);
  });
  
  // Auto hide after 10 seconds
  setTimeout(() => {
    if (el.style.display !== 'none') {
      el.style.opacity = '0';
      el.style.transform = 'translateX(100%)';
      setTimeout(() => {
        el.style.display = 'none';
      }, 300);
    }
  }, 10000);
});
</script>
<?php endif; ?>