<?php
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/security.php';
$pageTitle = "Главная страница";
include_once __DIR__ . '/includes/db.php';

// Параметры Turnstile sitekey (из того что ты дал)
$turnstile_sitekey = '0x4AAAAAABzFgQHD_KaZTnsZ';

// Популярные товары (как у тебя было, лимит 6)
$stmt = $pdo->prepare("SELECT * FROM products WHERE is_popular = 1 LIMIT 6");
$stmt->execute();
$popularProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Партнёры
$stmt = $pdo->prepare("SELECT * FROM partners");
$stmt->execute();
$partners = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Получение approved отзывов (показываем только approved)
$revStmt = $pdo->prepare("SELECT r.*, u.name AS user_name FROM reviews r LEFT JOIN users u ON r.user_id = u.id WHERE r.status = 'approved' ORDER BY r.created_at DESC LIMIT 6");
$revStmt->execute();
$reviews = $revStmt->fetchAll(PDO::FETCH_ASSOC);

// Функция получения главного изображения (как у тебя)
function getProductMainImage($pdo, $product_id) {
    $stmt = $pdo->prepare("SELECT image_url FROM product_images WHERE product_id = ? AND is_main = 1 LIMIT 1");
    $stmt->execute([$product_id]);
    return $stmt->fetchColumn();
}
?>
<?php include_once __DIR__ . '/includes/header.php'; ?>

<main class="font-sans bg-pattern min-h-screen">

    <!-- HERO: canvas dot-grid -->
  <section class="relative overflow-hidden h-[560px] md:h-[680px]">
    <!-- gradient base -->
    <div class="absolute inset-0 bg-gradient-to-r from-[#118568] to-[#17B890] z-0"></div>

    <!-- canvas for animated dot-grid -->
    <canvas id="dot-grid-canvas" class="absolute inset-0 w-full h-full z-10"></canvas>

    <div class="container mx-auto px-4 py-16 md:py-28 relative z-20 flex items-center h-full">
      <div class="max-w-3xl mx-auto text-center text-white">
        <h2 class="text-lg md:text-xl text-white/90 mb-2 font-medium">Создаём яркие решения для бизнеса и частных клиентов</h2>
        <h1 class="text-3xl md:text-5xl font-extrabold text-white mb-4 leading-tight">BZK PRINT</h1>
        <p class="text-base md:text-lg text-white/90 mb-8">От визиток до масштабных проектов - качество, которое видно и ощущается. Мы делаем так, чтобы клиенты возвращались снова.</p>

        <div class="flex flex-col sm:flex-row gap-4 justify-center">
          <a href="/catalog" class="inline-flex items-center justify-center px-8 py-4 bg-white text-[#118568] rounded-xl hover:scale-105 transition transform font-semibold shadow-lg">
            Перейти в каталог
          </a>
          <a href="/contacts" class="inline-flex items-center justify-center px-8 py-4 bg-transparent border-2 border-white text-white rounded-xl hover:bg-white hover:text-[#118568] transition transform font-semibold">
            Связаться с нами
          </a>
        </div>
      </div>
    </div>

    <!-- decorative floating element -->
    <div class="absolute -bottom-12 right-8 opacity-30 animate-slow-rotate w-44 h-44 rounded-full bg-white/5 pointer-events-none z-30"></div>
  </section>

  <!-- QUICK STATS + HOW WE WORK -->
  <section class="py-12 bg-white">
    <div class="container mx-auto px-4 max-w-7xl">
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 items-center mb-10">
        <div class="lg:col-span-2">
          <div class="text-2xl font-bold text-gray-800 mb-2">Как мы работаем</div>
          <p class="text-gray-600 mb-6">Простая и прозрачная схема от заказа до готовности. Подключим дизайнеров и менеджеров под ваш проект.</p>
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
            <?php
            $steps = [
              ['icon'=>'<svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>', 'title'=>'Заказ'],
              ['icon'=>'<svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>', 'title'=>'Макет'],
              ['icon'=>'<svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>', 'title'=>'Производство'],
              ['icon'=>'<svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" /></svg>', 'title'=>'Готовый заказ']
            ];
            foreach ($steps as $i => $s): ?>
              <div class="p-4 bg-[#F8FAF8] rounded-2xl flex flex-col items-center text-center transform transition hover:-translate-y-1" data-animate>
                <div class="w-12 h-12 rounded-full bg-[#118568] text-white flex items-center justify-center mb-3"><?php echo $s['icon']; ?></div>
                <div class="font-semibold text-gray-800"><?php echo $s['title']; ?></div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="bg-gradient-to-br from-[#F6FFFA] to-white rounded-2xl p-6 shadow-md">
          <div class="text-gray-700 font-medium mb-4">Наша статистика</div>
          <div class="grid grid-cols-2 gap-4">
            <div class="text-center">
              <div class="text-3xl font-bold text-[#118568]">100+</div>
              <div class="text-sm text-gray-600">Проектов</div>
            </div>
            <div class="text-center">
              <div class="text-3xl font-bold text-[#17B890]">4+</div>
              <div class="text-sm text-gray-600">Лет опыта</div>
            </div>
            <div class="text-center">
              <div class="text-3xl font-bold text-[#5E807F]">100к+</div>
              <div class="text-sm text-gray-600">Отпечатаных страниц/мес</div>
            </div>
            <div class="text-center">
              <div class="text-3xl font-bold text-[#118568]">99%</div>
              <div class="text-sm text-gray-600">Удовлетворённость</div>
            </div>
          </div>
        </div>
      </div>

      <!-- WHY CHOOSE US -->
      <div class="mb-10">
        <h3 class="text-2xl font-bold text-gray-800 mb-3">Почему выбирают нас</h3>
        <p class="text-gray-600 mb-6">Мы совмещаем технологичность и внимание к деталям.</p>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
          <div class="bg-white rounded-2xl p-6 shadow-md hover:shadow-xl transform transition hover:-translate-y-2" data-animate>
            <div class="w-14 h-14 rounded-lg bg-[#118568] text-white flex items-center justify-center mb-4">
              <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
              </svg>
            </div>
            <h4 class="font-semibold mb-2">Быстрая и надёжная печать</h4>
            <p class="text-gray-600 text-sm">Мы используем профессиональное оборудование для стабильного результата и контроля качества.</p>
          </div>

          <div class="bg-white rounded-2xl p-6 shadow-md hover:shadow-xl transform transition hover:-translate-y-2" data-animate>
            <div class="w-14 h-14 rounded-lg bg-[#17B890] text-white flex items-center justify-center mb-4">
              <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01" />
              </svg>
            </div>
            <h4 class="font-semibold mb-2">Индивидуальные решения</h4>
            <p class="text-gray-600 text-sm">Каждый проект уникален: подбираем материалы, тираж и технологии под Вашу задачу и бюджет.</p>
          </div>

          <div class="bg-white rounded-2xl p-6 shadow-md hover:shadow-xl transform transition hover:-translate-y-2" data-animate>
            <div class="w-14 h-14 rounded-lg bg-[#5E807F] text-white flex items-center justify-center mb-4">
              <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
              </svg>
            </div>
            <h4 class="font-semibold mb-2">Отслеживание заказа</h4>
            <p class="text-gray-600 text-sm">На странице заказа вы можете отслеживать текущий этап производства, а также задать вопросы или внести изменения - просто напишите в онлайн-чат.</p>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- POPULAR PRODUCTS -->
  <section class="py-12 bg-gradient-to-b from-white to-[#DEE5E5]">
    <div class="container mx-auto px-4 max-w-7xl">
      <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-8 gap-4">
        <div>
          <h3 class="text-2xl font-bold text-gray-800">Популярные товары</h3>
          <p class="text-gray-600">Товары, которые выбирают чаще всего</p>
        </div>
        <a href="/catalog" class="text-sm text-[#118568] font-medium self-start md:self-auto">Смотреть весь каталог →</a>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
        <?php foreach ($popularProducts as $product):
          $main_image = getProductMainImage($pdo, $product['id']);
          $image_url = $main_image ?: '/assets/images/no-image.webp';
        ?>
          <a href="/service?id=<?php echo $product['id']; ?>" class="group block bg-white rounded-2xl shadow-md overflow-hidden transform transition-all duration-300 hover:-translate-y-2" data-animate>
            <div class="relative overflow-hidden">
              <img src="<?php echo htmlspecialchars($image_url); ?>" loading="lazy"
                   alt="<?php echo htmlspecialchars($product['name']); ?>"
                   class="w-full h-44 object-cover transition-transform duration-500 group-hover:scale-110">
              <div class="absolute top-3 left-3">
                <span class="px-3 py-1 bg-[#17B890] text-white text-xs font-bold rounded-full">ПОПУЛЯРНОЕ</span>
              </div>
            </div>

            <div class="p-4">
              <h4 class="font-semibold text-gray-800 mb-1 line-clamp-2"><?php echo htmlspecialchars($product['name']); ?></h4>
              <p class="text-sm text-gray-600 line-clamp-2 mb-3"><?php echo htmlspecialchars($product['description']); ?></p>
              <div class="flex items-center justify-between">
                <div class="text-lg font-bold text-[#118568]">от <?php echo number_format($product['base_price'],0,'',' '); ?> ₽</div>
                <div class="text-white bg-[#118568] w-9 h-9 rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 transition">➜</div>
              </div>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- OUR WORKS (placeholders) -->
  <!--<section class="py-12 bg-white">
    <div class="container mx-auto px-4 max-w-7xl">
      <div class="flex items-center justify-between mb-8">
        <div>
          <h3 class="text-2xl font-bold text-gray-800">Наши работы</h3>
          <p class="text-gray-600">Небольшая подборка завершённых проектов (заглушки).</p>
        </div>
        <a href="/portfolio" class="text-sm text-[#118568] font-medium">Посмотреть портфолио →</a>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php for ($i=1; $i<=6; $i++): ?>
          <div class="bg-white rounded-2xl shadow-md overflow-hidden transform transition hover:-translate-y-2" data-animate>
            <div class="w-full h-48 bg-gray-100 flex items-center justify-center">
              <div class="text-gray-400">Заглушка проекта #<?php echo $i; ?></div>
            </div>
            <div class="p-4">
              <h4 class="font-semibold mb-1">Проект #<?php echo $i; ?></h4>
              <p class="text-sm text-gray-600">Краткое описание проекта, материалы и применённые технологии.</p>
            </div>
          </div>
        <?php endfor; ?>
      </div>
    </div>
  </section> -->

  <!-- REVIEWS -->
  <section class="py-12 bg-white">
    <div class="container mx-auto px-4 max-w-7xl">
      <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6 gap-4">
        <div>
          <h3 class="text-2xl font-bold text-gray-800">Отзывы клиентов</h3>
          <p class="text-gray-600">Реальные отзывы наших клиентов</p>
        </div>
        <button id="open-review-modal" class="px-4 py-2 bg-[#118568] text-white rounded-lg hover:bg-[#0f755a] transition self-start md:self-auto">Оставить отзыв</button>
      </div>

      <!-- reviews list -->
      <div id="reviews-list" class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <?php if (empty($reviews)): ?>
          <div class="text-gray-600">Пока нет одобренных отзывов.</div>
        <?php else: ?>
          <?php foreach ($reviews as $r): ?>
            <div class="bg-white rounded-2xl shadow-md p-6" data-animate>
              <div class="flex items-start gap-4">
                <div class="w-12 h-12 bg-[#DEE5E5] rounded-full flex items-center justify-center text-xl"><?php echo htmlspecialchars(mb_substr($r['name'] ?? $r['user_name'] ?? 'К',0,1)); ?></div>
                <div>
                  <div class="flex flex-wrap items-center gap-3">
                    <div class="font-semibold"><?php echo htmlspecialchars($r['name'] ?? $r['user_name'] ?? 'Пользователь'); ?></div>
                    <div class="text-sm text-gray-500"><?php echo date('d.m.Y', strtotime($r['created_at'])); ?></div>
                  </div>
                  <div class="mt-3 text-gray-700"><?php echo nl2br(htmlspecialchars($r['message'])); ?></div>
                  <div class="mt-3 text-sm text-yellow-600">Рейтинг: <?php echo intval($r['rating']); ?>/5</div>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <!-- PARTNERS -->
  <section class="py-12 bg-gradient-to-b from-[#DEE5E5] to-white">
    <div class="container mx-auto px-4 max-w-7xl">
      <div class="text-center mb-6">
        <h3 class="text-2xl font-bold text-gray-800">Наши партнёры</h3>
      </div>
      <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-6 items-center">
        <?php if (!empty($partners)): ?>
          <?php foreach ($partners as $p): ?>
            <div class="flex items-center justify-center p-4 bg-white rounded-2xl shadow-sm hover:shadow-md transform transition hover:scale-105">
              <img src="<?php echo htmlspecialchars($p['logo_url']); ?>" alt="<?php echo htmlspecialchars($p['name']); ?>" class="max-h-12 object-contain" loading="lazy">
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <?php for ($i=0;$i<6;$i++): ?>
            <div class="flex items-center justify-center p-4 bg-white rounded-2xl shadow-sm">
              <div class="h-10 w-32 bg-gray-200 rounded-lg"></div>
            </div>
          <?php endfor; ?>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <!-- CTA -->
  <section class="py-12 bg-gradient-to-r from-[#118568] to-[#17B890]">
    <div class="container mx-auto px-4 max-w-4xl text-center">
      <h3 class="text-2xl md:text-3xl text-white font-bold mb-4">Готовы начать проект?</h3>
      <p class="text-white/90 mb-6">Свяжитесь с нами и мы поможем подобрать лучшее решение для вашего случая.</p>
      <div class="flex flex-col sm:flex-row gap-4 justify-center">
        <a href="/contacts" class="px-6 py-3 bg-white text-[#118568] rounded-lg font-semibold">Связаться</a>
        <a href="/catalog" class="px-6 py-3 bg-transparent border border-white text-white rounded-lg font-semibold">Каталог</a>
      </div>
    </div>
  </section>

</main>

<!-- REVIEW MODAL (hidden by default) -->
<div id="review-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40">
  <div class="bg-white rounded-2xl w-full max-w-2xl mx-4 p-6 relative">
    <button id="close-review-modal" class="absolute right-4 top-4 text-gray-500 hover:text-gray-700">✕</button>

    <h4 class="text-xl font-bold mb-3">Оставить отзыв</h4>

    <?php if (!isset($_SESSION['user_id'])): ?>
      <div id="login-required" class="p-6 bg-yellow-50 rounded-lg">
        <p class="mb-4">Чтобы оставить отзыв, пожалуйста, <a href="/login" class="text-[#118568] underline">войдите в аккаунт</a>.</p>
      </div>
    <?php else: ?>
      <form id="review-form" class="space-y-4">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="product_id" value="">
        <div>
          <label class="block text-sm font-medium text-gray-700">Ваше имя</label>
          <input type="text" name="name" class="mt-1 block w-full border rounded-lg px-3 py-2" value="<?php echo htmlspecialchars($_SESSION['user_name'] ?? ''); ?>" required>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700">Рейтинг</label>
          <div class="flex items-center gap-2 mt-2">
            <?php for($i=5;$i>=1;$i--): ?>
              <label class="cursor-pointer">
                <input type="radio" name="rating" value="<?php echo $i; ?>" class="hidden" <?php echo $i===5 ? 'checked' : ''; ?>>
                <span class="px-3 py-2 rounded-md bg-[#F3F4F6] hover:bg-[#E6F2EC]"><?php echo $i; ?>★</span>
              </label>
            <?php endfor; ?>
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700">Отзыв</label>
          <textarea name="message" rows="5" required class="mt-1 block w-full border rounded-lg px-3 py-2"></textarea>
        </div>

        <!-- Turnstile widget -->
        <div class="cf-turnstile" data-sitekey="<?php echo htmlspecialchars($turnstile_sitekey); ?>"></div>

        <div id="review-error" class="text-red-600 hidden"></div>
        <div class="flex gap-3">
          <button type="submit" class="ml-auto px-6 py-3 bg-[#118568] text-white rounded-lg">Отправить</button>
        </div>
      </form>
    <?php endif; ?>
  </div>
</div>

<!-- Scripts -->
<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>

<script>
// ---- Canvas dot-grid animation ----
// ---- Canvas dot-grid animation (Updated) ----
(function(){
  const canvas = document.getElementById('dot-grid-canvas');
  if (!canvas) return;
  const ctx = canvas.getContext('2d');

  let DPR = window.devicePixelRatio || 1;
  let resizeTimeout = null;

  // config
  const config = {
    dotSpacing: 28, // Расстояние между точками
    dotRadius: 1.1, // Радиус точки
    baseAlpha: 0.45, // Базовая прозрачность (увеличил для лучшей видимости поверх градиента)
    speed: 0.18, // Скорость движения
    parallaxStrength: 0.06
  };

  let width = 0, height = 0;
  let offsetX = 0, offsetY = 0;
  let time = 0;
  let mouseX = 0, mouseY = 0, hasPointer = false;

  function resize() {
    DPR = window.devicePixelRatio || 1;
    width = canvas.clientWidth;
    height = canvas.clientHeight;
    canvas.width = Math.floor(width * DPR);
    canvas.height = Math.floor(height * DPR);
    // Убедимся, что трансформация учитывает DPR
    ctx.setTransform(DPR,0,0,DPR,0,0);
  }

  function draw() {
    // Очищаем с прозрачностью, чтобы точки оставались "в памяти" и создавали эффект следа (опционально)
    // ctx.clearRect(0,0,width,height);
    // Или просто очищаем
    ctx.clearRect(0,0,width,height);

    const spacing = config.dotSpacing;
    const r = config.dotRadius;
    const cols = Math.ceil(width / spacing) + 2;
    const rows = Math.ceil(height / spacing) + 2;

    // parallax offset by mouse
    const px = (mouseX - width/2) * config.parallaxStrength;
    const py = (mouseY - height/2) * config.parallaxStrength;

    // animate drifting
    offsetX -= config.speed;
    offsetY -= config.speed * 0.3;

    // Установим цвет точек. Белый с прозрачностью.
    // Цвет подобран для контраста с зелёным градиентом.
    ctx.fillStyle = `rgba(255, 255, 255, ${config.baseAlpha})`;

    for (let i = -1; i < cols; i++){
      for (let j = -1; j < rows; j++){
        const x = (i * spacing) + (offsetX % spacing) + px * (i/cols*0.6);
        const y = (j * spacing) + (offsetY % spacing) + py * (j/rows*0.6);

        // Рисуем точку
        ctx.beginPath();
        ctx.arc(x, y, r, 0, Math.PI*2);
        ctx.fill();
      }
    }

    time++;
    requestAnimationFrame(draw);
  }

  // pointer events to influence parallax
  window.addEventListener('pointermove', (e)=>{
    const rect = canvas.getBoundingClientRect();
    mouseX = e.clientX - rect.left;
    mouseY = e.clientY - rect.top;
    hasPointer = true;
  });
  window.addEventListener('pointerleave', ()=>{ hasPointer = false; });

  // mobile touch fallback
  window.addEventListener('touchmove', (e)=>{
    if (!e.touches || !e.touches[0]) return;
    const rect = canvas.getBoundingClientRect();
    mouseX = e.touches[0].clientX - rect.left;
    mouseY = e.touches[0].clientY - rect.top;
  }, {passive:true});

  window.addEventListener('resize', ()=>{
    clearTimeout(resizeTimeout);
    resizeTimeout = setTimeout(()=>{ resize(); }, 120);
  });

  // init
  resize();
  requestAnimationFrame(draw);
})();

// Simple IntersectionObserver reveal with stagger
(function(){
  const nodes = Array.from(document.querySelectorAll('[data-animate]'));
  if (!nodes.length) return;

  // initialize: make sure children have initial hidden state
  nodes.forEach(el => {
    // if container has multiple direct children we animate them in sequence,
    // otherwise animate the element itself.
    const directChildren = Array.from(el.querySelectorAll(':scope > *'));
    if (directChildren.length > 1) {
      directChildren.forEach(ch => {
        ch.style.opacity = '0';
        ch.style.transform = 'translateY(18px)';
        ch.style.willChange = 'opacity, transform';
      });
    } else {
      el.style.opacity = '0';
      el.style.transform = 'translateY(18px)';
      el.style.willChange = 'opacity, transform';
    }
  });

  const io = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (!entry.isIntersecting) return;
      const el = entry.target;
      const directChildren = Array.from(el.querySelectorAll(':scope > *'));
      // function to apply final stable styles after animation
      function finalize(node) {
        // ensure final visible state; remove transition to prevent flicker later
        node.style.opacity = '1';
        node.style.transform = 'none';
        node.style.transition = '';
        node.style.willChange = '';
      }

      if (directChildren.length > 1) {
        // staggered reveal for children
        directChildren.forEach((ch, idx) => {
          // small timeout to create stagger (use rAF to ensure paint)
          const delay = idx * 70; // ms, tweakable
          ch.style.transition = `opacity 420ms cubic-bezier(.2,.8,.2,1) ${delay}ms, transform 420ms cubic-bezier(.2,.8,.2,1) ${delay}ms`;
          // trigger in next frame to ensure transition applies
          requestAnimationFrame(() => {
            ch.style.opacity = '1';
            ch.style.transform = 'translateY(0)';
          });
          // after transition, finalize
          setTimeout(() => finalize(ch), delay + 450);
        });
      } else {
        // single element reveal
        el.style.transition = 'opacity 520ms cubic-bezier(.2,.8,.2,1), transform 520ms cubic-bezier(.2,.8,.2,1)';
        requestAnimationFrame(() => {
          el.style.opacity = '1';
          el.style.transform = 'translateY(0)';
        });
        setTimeout(() => finalize(el), 560);
      }

      // stop observing to avoid re-running animations / style conflicts
      io.unobserve(el);
    });
  }, { threshold: 0.15 });

  nodes.forEach(n => io.observe(n));
})();

// Modal logic + Review AJAX (reused + improved)
(function(){
  const modal = document.getElementById('review-modal');
  const openBtn = document.getElementById('open-review-modal');
  const closeBtn = document.getElementById('close-review-modal');

  if (!openBtn) return;
  openBtn.addEventListener('click', function(){
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    // reset messages
    const err = document.getElementById('review-error'); if (err) { err.classList.add('hidden'); err.textContent=''; }
    // Reset Turnstile widget if exists (CF auto-inits)
    if (window.turnstile && typeof turnstile !== 'undefined' && turnstile.render) {
      // no-op: widget auto-renders on element with class cf-turnstile
    }
  });

  if (closeBtn) closeBtn.addEventListener('click', ()=>{ modal.classList.add('hidden'); modal.classList.remove('flex'); });

  // close on background click
  modal.addEventListener('click', function(e){
    if (e.target === modal) { modal.classList.add('hidden'); modal.classList.remove('flex'); }
  });

  // handle form AJAX
  const form = document.getElementById('review-form');
  if (!form) return;

  form.addEventListener('submit', async function(e){
    e.preventDefault();
    const btn = form.querySelector('button[type="submit"]');
    btn.disabled = true;
    btn.textContent = 'Отправка...';

    const fd = new FormData(form);
    // gather Turnstile response if present
    // CF widget usually injects input name="cf-turnstile-response"
    if (!fd.has('cf-turnstile-response') && window.turnstile && typeof turnstile !== 'undefined' && turnstile.getResponse) {
      try {
        const token = await turnstile.getResponse();
        if (token) fd.append('cf-turnstile-response', token);
      } catch(e){}
    }

    try {
      const res = await fetch('/includes/add_review.php', {
        method: 'POST',
        credentials: 'same-origin',
        body: fd
      });
      const json = await res.json();
      if (json.success) {
        form.innerHTML = '<div class="p-6 bg-green-50 rounded-md text-green-800">Спасибо! Ваш отзыв отправлен и будет опубликован после модерации.</div>';
        // optionally: disable open button for a while to prevent spam
      } else {
        const err = document.getElementById('review-error');
        err.classList.remove('hidden');
        err.textContent = json.message || 'Ошибка отправки. Попробуйте ещё.';
        btn.disabled = false;
        btn.textContent = 'Отправить';
      }
    } catch (err) {
      console.error(err);
      const errEl = document.getElementById('review-error');
      if (errEl) { errEl.classList.remove('hidden'); errEl.textContent = 'Ошибка сети. Попробуйте ещё раз.'; }
      btn.disabled = false;
      btn.textContent = 'Отправить';
    }
  });

})();

</script>

<!-- Minimal extra styles -->
<style>
  .animate-slow-rotate { animation: float-rotate 12s linear infinite; }
  @keyframes float-rotate {
    0% { transform: translateY(0) rotate(0deg); }
    50% { transform: translateY(-8px) rotate(10deg); }
    100% { transform: translateY(0) rotate(0deg); }
  }

  /* Small utilities to smooth hover */
  .line-clamp-2 { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
  .line-clamp-3 { display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
  /* Slight visual for canvas overlay to avoid too bright */
  #dot-grid-canvas {
  /* mix-blend-mode: overlay; */ /* Убедитесь, что это закомментировано или удалено */
  z-index: 10; /* Убедитесь, что z-index указан в HTML, если еще не сделали */
}
</style>

<?php include_once __DIR__ . '/includes/footer.php'; ?>