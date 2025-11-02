<?php
require_once __DIR__ . '/includes/session.php';
$pageTitle = "Каталог";
include_once __DIR__ . '/includes/db.php';

// --------------------
// Параметры
// --------------------
$category_id = $_GET['category'] ?? null;
$type = $_GET['type'] ?? 'product';
$sort = $_GET['sort'] ?? 'default';
$page = max(1, (int)($_GET['page'] ?? 1));
$isAjax = isset($_GET['ajax']) && $_GET['ajax'] == '1';

// Пагинация: per_page можно передать ?per_page=8|16|32
$perPage = (int)($_GET['per_page'] ?? 8);
if (!in_array($perPage, [8,16,32])) $perPage = 8;
$offset = ($page - 1) * $perPage;

// --------------------
// Категории
// --------------------
$cat_stmt = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC");
$categories = $cat_stmt->fetchAll(PDO::FETCH_ASSOC);

// --------------------
// Подсчёт общего количества товаров (COUNT(*) вместо SQL_CALC_FOUND_ROWS)
// --------------------
$countQuery = "SELECT COUNT(*) FROM products WHERE type = :type";
$countParams = [':type' => $type];
if ($category_id) {
    $countQuery .= " AND category_id = :category_id";
    $countParams[':category_id'] = $category_id;
}
$countStmt = $pdo->prepare($countQuery);
foreach ($countParams as $k => $v) $countStmt->bindValue($k, $v);
$countStmt->execute();
$total = (int)$countStmt->fetchColumn();
$totalPages = $perPage > 0 ? (int)ceil($total / $perPage) : 1;

// --------------------
// Запрос товаров (с LIMIT/OFFSET)
// --------------------
$query = "SELECT p.*, c.name AS category_name
          FROM products p
          LEFT JOIN categories c ON p.category_id = c.id
          WHERE p.type = :type";

$params = [':type' => $type];
if ($category_id) {
    $query .= " AND p.category_id = :category_id";
    $params[':category_id'] = $category_id;
}
if ($sort === 'price_asc') $query .= " ORDER BY p.base_price ASC";
elseif ($sort === 'price_desc') $query .= " ORDER BY p.base_price DESC";
elseif ($sort === 'popularity') $query .= " ORDER BY p.is_popular DESC";

$query .= " LIMIT :limit OFFSET :offset";
$stmt = $pdo->prepare($query);
foreach ($params as $k => $v) $stmt->bindValue($k, $v);
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --------------------
// Функция изображения
// --------------------
function getProductMainImage($pdo, $product_id) {
    $st = $pdo->prepare("SELECT image_url FROM product_images WHERE product_id = ? AND is_main = 1 LIMIT 1");
    $st->execute([$product_id]);
    $r = $st->fetchColumn();
    return $r ? $r : '/assets/images/no-image.webp';
}

// --------------------
// AJAX: вернуть только карточки (grid) + meta с total
// --------------------
if ($isAjax) {
    // выведем метаданные (total) в виде скрытого блока, чтобы фронтенд мог получить точное значение
    echo '<div id="ajax-meta" data-total="'.htmlspecialchars($total, ENT_QUOTES).'"></div>';

    if (empty($products)) {
        // ничего не найдено — фронтэнд отобразит пустой контейнер
        echo '';
        exit;
    }

    // grid view only
    foreach ($products as $product):
        $img = getProductMainImage($pdo, $product['id']);
        ?>
        <a href="<?php echo $type==='product'?'/service?id='.$product['id']:'/service?id='.$product['id'].'&type=service'; ?>" 
           class="card-item group block bg-white rounded-2xl shadow-md overflow-hidden transform transition-all duration-500 hover:-translate-y-2 hover:shadow-2xl">
          <div class="relative overflow-hidden">
            <img src="<?php echo htmlspecialchars($img);?>" alt="<?php echo htmlspecialchars($product['name']);?>" class="w-full h-56 object-cover transition-transform duration-700 group-hover:scale-110">
            <div class="absolute inset-0 bg-gradient-to-t from-black/30 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
            <button class="fav-btn absolute top-3 right-3 bg-white/90 rounded-full p-2 shadow transition hover:scale-110" data-id="<?php echo $product['id'];?>">
              <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-gray-500 transition" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 016.364 0L12 7.636l1.318-1.318a4.5 4.5 0 116.364 6.364L12 21.364l-7.682-8.682a4.5 4.5 0 010-6.364z" /></svg>
            </button>
          </div>
          <div class="p-6 flex flex-col h-full">
            <h3 class="text-lg font-bold text-gray-800 mb-1 line-clamp-2 group-hover:text-[#118568] transition"><?php echo htmlspecialchars($product['name']); ?></h3>
            <p class="text-gray-600 text-sm flex-grow line-clamp-3"><?php echo htmlspecialchars($product['description']); ?></p>
            <div class="mt-4 flex justify-between items-center">
              <div class="text-xl font-extrabold text-[#118568]">от <?php echo number_format($product['base_price'],0,'',' '); ?> ₽</div>
              <?php if ($type==='product' && isset($product['in_stock'])): ?>
                <span class="text-xs px-2 py-1 rounded-full <?php echo $product['in_stock']>0?'bg-green-100 text-green-700':'bg-red-100 text-red-700'; ?>">
                  <?php echo $product['in_stock']>0 ? 'В наличии' : 'Под заказ'; ?>
                </span>
              <?php endif; ?>
            </div>
          </div>
        </a>
        <?php
    endforeach;

    exit;
}

// --------------------
// Если не AJAX — полный рендер страницы
// --------------------
include_once __DIR__ . '/includes/header.php';
?>
<main class="min-h-screen from-[#DEE5E5] to-[#9DC5BB] bg-pattern py-8 relative overflow-hidden">
  <div class="container mx-auto px-4 max-w-7xl relative z-10">
<!-- Вставка breadcrumbs и кнопки "Назад" -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
            <div class="w-full md:w-auto">
                <?php echo generateBreadcrumbs($pageTitle ?? ''); ?>
            </div>
            <div class="w-full md:w-auto">
                <?php echo backButton(); ?>
            </div>
        </div>
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-6 mb-6">
      <div>
        <h1 class="text-4xl md:text-5xl font-extrabold text-gray-800">Каталог</h1>
        <p class="text-gray-600 mt-2">Фильтры, быстрый доступ к избранному и плавная загрузка товаров.</p>
        <div class="w-24 h-1 bg-gradient-to-r from-[#118568] to-[#17B890] rounded-full mt-4"></div>
      </div>
    </div>

    <!-- Categories chips + reset button -->
    <div class="mb-6 flex flex-wrap items-center gap-3">
      <div class="flex gap-3 overflow-x-auto no-scrollbar">
        <button class="category-chip px-4 py-2 rounded-full border border-gray-200 bg-white text-sm text-gray-700 hover:bg-[#EEF6F3] transition" data-value="">Все</button>
        <?php foreach ($categories as $cat): ?>
          <button class="category-chip px-4 py-2 rounded-full border border-gray-200 bg-white text-sm text-gray-700 hover:bg-[#EEF6F3] transition" data-value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></button>
        <?php endforeach; ?>
      </div>

      <div class="ml-auto flex items-center gap-3">
        <div class="text-sm text-gray-600">Активные: <span id="active-filters" class="font-medium text-[#118568] ml-2">Нет</span></div>
        <button id="reset-filters" class="text-sm text-gray-600 hover:text-[#118568]">Сбросить</button>
      </div>
    </div>

    <!-- Filter panel -->
    <div id="filter-panel" class="sticky top-4 z-40 bg-white/90 backdrop-blur-md border border-gray-100 rounded-2xl shadow-lg p-4 mb-6">
      <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
        <div class="flex items-center gap-3">
          <div class="text-sm font-semibold text-gray-700">Тип:</div>
          <div class="flex rounded-lg overflow-hidden border-2 border-[#118568]">
            <a href="#" class="type-btn px-4 py-2 text-sm font-medium transition <?php echo $type==='product'?'bg-[#118568] text-white':''; ?>" data-value="product">Товары</a>
            <a href="#" class="type-btn px-4 py-2 text-sm font-medium transition <?php echo $type==='service'?'bg-[#118568] text-white':''; ?>" data-value="service">Услуги</a>
          </div>
        </div>

        <div class="flex items-center gap-3">
          <label class="text-sm text-gray-700">Сортировка</label>
          <select id="sort-select" class="px-3 py-2 border rounded-lg text-sm">
            <option value="default" <?php echo $sort==='default'?'selected':''; ?>>По умолчанию</option>
            <option value="price_asc" <?php echo $sort==='price_asc'?'selected':''; ?>>Цена ↑</option>
            <option value="price_desc" <?php echo $sort==='price_desc'?'selected':''; ?>>Цена ↓</option>
            <option value="popularity" <?php echo $sort==='popularity'?'selected':''; ?>>Популярность</option>
          </select>
        </div>

        <div class="text-sm text-gray-600">Найдено: <span id="product-count" class="font-bold text-[#118568]"><?php echo $total; ?></span></div>
      </div>
    </div>

    <!-- Products container (initial server render) -->
<div id="products-container" class="min-h-[200px]">
  <div id="catalog-grid" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-8">
    <?php if (empty($products)): ?>
      <div class="bg-white/70 rounded-2xl shadow-xl p-12 text-center">
        <div class="text-6xl mb-4">🔍</div>
        <h3 class="text-2xl font-bold text-gray-800 mb-2">Ничего не найдено</h3>
        <p class="text-gray-600 mb-6">Попробуйте изменить фильтры.</p>
      </div>
    <?php else: ?>
      <?php foreach ($products as $product): $img = getProductMainImage($pdo, $product['id']); ?>
        <a href="<?php echo $type==='product'?'/service?id='.$product['id']:'/service?id='.$product['id'].'&type=service'; ?>" 
           class="card-item group relative block bg-white rounded-2xl shadow-md overflow-hidden transform transition-all duration-500 hover:-translate-y-2 hover:shadow-[0_10px_30px_rgba(17,133,104,0.15)]">

          <!-- Изображение -->
          <div class="relative overflow-hidden">
            <img src="<?php echo htmlspecialchars($img);?>" 
                 alt="<?php echo htmlspecialchars($product['name']);?>" 
                 class="w-full h-56 object-cover transition-transform duration-700 group-hover:scale-110">
            
            <div class="absolute inset-0 bg-gradient-to-t from-black/50 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>

            <!-- Категория -->
            <?php if (!empty($product['category_name'])): ?>
              <div class="absolute top-3 left-3">
                <span class="px-3 py-1 bg-[#118568]/90 text-white text-xs font-semibold rounded-full shadow-sm">
                  <?php echo htmlspecialchars($product['category_name']); ?>
                </span>
              </div>
            <?php endif; ?>

            <!-- Избранное -->
            <button class="fav-btn absolute top-3 right-3 bg-white/90 rounded-full p-2 shadow transition hover:scale-110 hover:bg-[#118568]/10" data-id="<?php echo $product['id'];?>">
              <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-gray-500 transition-all duration-300 group-hover:drop-shadow-[0_0_4px_rgba(255,0,0,0.4)]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 016.364 0L12 7.636l1.318-1.318a4.5 4.5 0 116.364 6.364L12 21.364l-7.682-8.682a4.5 4.5 0 010-6.364z" />
              </svg>
            </button>

            <!-- Кнопка "Подробнее" -->
            <div class="absolute bottom-3 left-1/2 -translate-x-1/2 opacity-0 group-hover:opacity-100 transition-all duration-300 transform group-hover:translate-y-0 translate-y-3">
              <div class="bg-[#118568] text-white px-5 py-2 rounded-full font-medium text-sm shadow-md hover:bg-[#0f755a] transition">
                Подробнее
              </div>
            </div>
          </div>

          <!-- Контент -->
          <div class="p-6 flex flex-col h-auto">
            <h3 class="text-lg font-bold text-gray-800 mb-2 group-hover:text-[#118568] transition-colors duration-300 line-clamp-2">
              <?php echo htmlspecialchars($product['name']); ?>
            </h3>

            <!-- Описание фиксированной высоты -->
            <p class="text-gray-600 text-sm flex-grow line-clamp-3 max-h-[50px]">
              <?php echo htmlspecialchars($product['description']); ?>
            </p>

            <!-- Цена и наличие -->
            <div class="mt-4 flex items-center justify-between border-t pt-3">
              <div class="text-lg font-extrabold text-[#118568] whitespace-nowrap">
                <?php if ($product['base_price']): ?>
                  от <?php echo number_format($product['base_price'],0,'',' '); ?> ₽
                <?php endif; ?>
              </div>
              <?php if ($type==='product' && isset($product['in_stock'])): ?>
                <span class="text-xs px-2 py-1 rounded-full <?php echo $product['in_stock']>0?'bg-green-100 text-green-700':'bg-red-100 text-red-700'; ?>">
                  <?php echo $product['in_stock']>0 ? 'В наличии' : 'Под заказ'; ?>
                </span>
              <?php endif; ?>
            </div>
          </div>
        </a>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>



    <!-- Load more -->
    <?php if ($totalPages > $page): ?>
      <div class="text-center mt-12">
        <button id="load-more" class="px-8 py-3 rounded-xl bg-[#118568] text-white font-medium hover:bg-[#0f755a] active:scale-95 transition-transform">Загрузить ещё</button>
        <div class="mt-3 text-sm text-gray-500">Показано <span id="shown-count"><?php echo min($page * $perPage, $total); ?></span> из <span id="total-count"><?php echo $total; ?></span> товаров</div>
      </div>
    <?php endif; ?>

  </div>
</main>

<!-- Скрипты: AJAX фильтрация/подгрузка, chips, избранное, сохранение state -->
<script>
(function(){
  // DOM
  const productsContainer = document.getElementById('products-container');
  const loadMoreBtn = document.getElementById('load-more');
  const categoryChips = document.querySelectorAll('.category-chip');
  const typeBtns = document.querySelectorAll('.type-btn');
  const sortSelect = document.getElementById('sort-select');
  const productCountEl = document.getElementById('product-count');
  const shownCountEl = document.getElementById('shown-count');
  const totalCountEl = document.getElementById('total-count');
  const resetBtn = document.getElementById('reset-filters');
  const activeFiltersEl = document.getElementById('active-filters');

  // State (инициализируем из PHP)
  let state = {
    type: '<?php echo addslashes($type); ?>',
    category: '<?php echo addslashes($category_id ?? ''); ?>',
    sort: '<?php echo addslashes($sort); ?>',
    page: <?php echo $page; ?>,
    per_page: <?php echo $perPage; ?>,
    totalPages: <?php echo $totalPages; ?>,
    total: <?php echo $total; ?>
  };

  // Load saved state from localStorage (type, per_page)
  try {
    const saved = JSON.parse(localStorage.getItem('catalog_state') || '{}');
    if (saved.type) state.type = saved.type;
    if (saved.per_page && [8,16,32].includes(saved.per_page)) state.per_page = saved.per_page;
  } catch(e){ /* ignore */ }

  // UI helpers
  function markActiveChips(){
    categoryChips.forEach(c => {
      if (c.dataset.value === (state.category || '')) {
        c.classList.add('active','bg-[#118568]','text-[#118568]','scale-105','shadow-sm');
        c.setAttribute('aria-pressed','true');
      } else {
        c.classList.remove('active','bg-[#118568]','text-[#118568]','scale-105','shadow-sm');
        c.setAttribute('aria-pressed','false');
      }
    });
  }
  function markTypeBtns(){
    typeBtns.forEach(b => {
      if (b.dataset.value === state.type) {
        b.classList.add('bg-[#118568]','text-white');
        b.setAttribute('aria-pressed','true');
      } else {
        b.classList.remove('bg-[#118568]','text-white');
        b.setAttribute('aria-pressed','false');
      }
    });
  }
  function updateActiveFiltersLabel(){
    const parts = [];
    if (state.category) {
      const el = document.querySelector('.category-chip[data-value="'+state.category+'"]');
      parts.push(el ? el.textContent.trim() : 'Категория');
    }
    if (state.sort && state.sort !== 'default') {
      const map = {'price_asc':'Цена ↑','price_desc':'Цена ↓','popularity':'Популярность'};
      parts.push(map[state.sort] || 'Сортировка');
    }
    if (state.type) parts.unshift(state.type === 'product' ? 'Товары' : 'Услуги');
    activeFiltersEl.textContent = parts.length ? parts.join(' · ') : 'Нет';
  }

  // Помощь — собрать URL ajax
  function buildAjaxUrl(page = 1) {
    const p = new URLSearchParams();
    p.set('ajax','1');
    p.set('type', state.type);
    if (state.category) p.set('category', state.category);
    if (state.sort && state.sort !== 'default') p.set('sort', state.sort);
    p.set('page', page);
    p.set('per_page', state.per_page);
    return '/catalog.php?' + p.toString();
  }

  // Заменить список (page=1)
  async function loadAndReplace() {
    state.page = 1;
    saveState();
    // skeleton
    productsContainer.innerHTML = '<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-8">' + '<div class="animate-pulse bg-white rounded-2xl h-80"></div>'.repeat(state.per_page) + '</div>';
    try {
      const res = await fetch(buildAjaxUrl(state.page), { credentials: 'same-origin' });
      if (!res.ok) throw new Error('network');
      const html = await res.text();
      // find meta total
      const tmp = document.createElement('div');
      tmp.innerHTML = html;
      const meta = tmp.querySelector('#ajax-meta');
      if (meta && meta.dataset && meta.dataset.total) {
        state.total = parseInt(meta.dataset.total, 10) || state.total;
        state.totalPages = Math.ceil(state.total / state.per_page);
      }
      // remove meta from HTML
      const metaNode = tmp.querySelector('#ajax-meta');
      if (metaNode) metaNode.remove();
      const cardsHtml = tmp.innerHTML.trim();
      productsContainer.innerHTML = `<div id="catalog-grid" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-8">` + cardsHtml + '</div>';
      // update counters / UI
      productCountEl && (productCountEl.textContent = state.total);
      shownCountEl && (shownCountEl.textContent = Math.min(state.page * state.per_page, state.total));
      totalCountEl && (totalCountEl.textContent = state.total);
      updateLoadMoreVisibility();
      markActiveChips();
      markTypeBtns();
      updateActiveFiltersLabel();
      syncFavorites();
      updateUrl();
    } catch (e) {
      console.error(e);
      productsContainer.innerHTML = '<div class="p-8 bg-white rounded-2xl text-red-600">Ошибка загрузки. Попробуйте ещё раз.</div>';
    }
  }

  // Подгрузить следующую страницу
  async function loadMore() {
    if (state.page >= state.totalPages) return;
    const nextPage = state.page + 1;
    loadMoreBtn.disabled = true;
    loadMoreBtn.textContent = 'Загрузка...';
    try {
      const res = await fetch(buildAjaxUrl(nextPage), { credentials: 'same-origin' });
      if (!res.ok) throw new Error('network');
      const html = await res.text();
      const tmp = document.createElement('div');
      tmp.innerHTML = html;
      const meta = tmp.querySelector('#ajax-meta');
      if (meta && meta.dataset.total) {
        state.total = parseInt(meta.dataset.total, 10) || state.total;
        state.totalPages = Math.ceil(state.total / state.per_page);
      }
      if (tmp.querySelector('#ajax-meta')) tmp.querySelector('#ajax-meta').remove();
      const items = tmp.querySelectorAll('.card-item');
      const grid = document.querySelector('#products-container #catalog-grid');
      items.forEach(item => {
        item.style.opacity = '0';
        grid.appendChild(item);
        setTimeout(()=>{ item.style.transition = 'opacity 400ms'; item.style.opacity = '1'; }, 50);
      });
      state.page = nextPage;
      shownCountEl && (shownCountEl.textContent = Math.min(state.page * state.per_page, state.total));
      totalCountEl && (totalCountEl.textContent = state.total);
      updateLoadMoreVisibility();
      syncFavorites();
      loadMoreBtn.disabled = false;
      loadMoreBtn.textContent = state.page >= state.totalPages ? 'Все товары загружены 🎉' : 'Загрузить ещё';
    } catch (e) {
      console.error(e);
      loadMoreBtn.textContent = 'Ошибка. Повторить';
      loadMoreBtn.disabled = false;
    }
  }

  function updateLoadMoreVisibility(){
    if (!loadMoreBtn) return;
    if (state.page >= state.totalPages) {
      loadMoreBtn.textContent = 'Все товары загружены 🎉';
      loadMoreBtn.disabled = true;
    } else {
      loadMoreBtn.textContent = 'Загрузить ещё';
      loadMoreBtn.disabled = false;
    }
  }

  function updateUrl(){
    const params = new URLSearchParams();
    params.set('type', state.type);
    if (state.category) params.set('category', state.category);
    if (state.sort && state.sort !== 'default') params.set('sort', state.sort);
    history.replaceState({}, '', '/catalog?' + params.toString());
  }

  // --------------------
  // Events
  // --------------------
  categoryChips.forEach(chip => {
    // make chips keyboard-focusable and clickable
    chip.setAttribute('tabindex', '0');
    chip.addEventListener('click', function(e){
      e.preventDefault();
      const val = this.dataset.value || '';
      // Toggle active state via JS (not CSS :focus) so it persists after blur
      if (state.category === val) {
        state.category = '';
      } else {
        state.category = val;
      }
      markActiveChips();
      loadAndReplace();
    });
    // support Enter / Space keys for accessibility
    chip.addEventListener('keydown', function(e){
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        this.click();
      }
    });
  });

  typeBtns.forEach(btn => {
    btn.addEventListener('click', function(e){
      e.preventDefault();
      const val = this.dataset.value;
      if (!val) return;
      state.type = val;
      state.category = '';
      markActiveChips();
      markTypeBtns();
      loadAndReplace();
    });
  });

  sortSelect && sortSelect.addEventListener('change', function(){
    state.sort = this.value;
    loadAndReplace();
  });

  loadMoreBtn && loadMoreBtn.addEventListener('click', function(){
    loadMore();
  });

  resetBtn && resetBtn.addEventListener('click', function(){
    // reset state (preserve per_page)
    state.type = 'product';
    state.category = '';
    state.sort = 'default';
    state.page = 1;
    // UI resets
    sortSelect && (sortSelect.value = 'default');
    markActiveChips();
    markTypeBtns();
    updateActiveFiltersLabel();
    loadAndReplace();
  });

  // --------------------
  // Избранное (localStorage)
  // --------------------
  function getFavs(){ try { return JSON.parse(localStorage.getItem('favorites')||'[]'); } catch { return []; } }
  function setFavs(f){ localStorage.setItem('favorites', JSON.stringify(f)); }
  function toggleFav(id){
    const favs = getFavs();
    const idx = favs.indexOf(id);
    if (idx === -1) favs.push(id); else favs.splice(idx,1);
    setFavs(favs);
    syncFavorites();
  }
  function syncFavorites(){
    const favs = getFavs();
    document.querySelectorAll('.fav-btn').forEach(btn=>{
      const id = parseInt(btn.dataset.id || 0);
      const svg = btn.querySelector('svg');
      if (favs.includes(id)) {
        svg.classList.add('text-[#e63946]','scale-110');
        svg.classList.remove('text-gray-500');
      } else {
        svg.classList.remove('text-[#e63946]','scale-110');
        svg.classList.add('text-gray-500');
      }
    });
  }
  document.addEventListener('click', function(e){
    const btn = e.target.closest('.fav-btn');
    if (!btn) return;
    e.preventDefault();
    const id = parseInt(btn.dataset.id || 0);
    if (!id) return;
    toggleFav(id);
    btn.classList.add('animate-bounce');
    setTimeout(()=>btn.classList.remove('animate-bounce'),600);
  });

  // --------------------
  // Save/Load state
  // --------------------
  function saveState(){
    try {
      localStorage.setItem('catalog_state', JSON.stringify({ type: state.type, per_page: state.per_page }));
    } catch(e){}
  }

  // init UI
  markActiveChips();
  markTypeBtns();
  updateActiveFiltersLabel();
  updateLoadMoreVisibility();
  syncFavorites();

  // If URL contains filters on first load — apply them
  (function initFromUrlOrState(){
    const u = new URLSearchParams(window.location.search);
    if (u.get('type')) state.type = u.get('type');
    if (u.get('category')) state.category = u.get('category');
    if (u.get('sort')) {
      state.sort = u.get('sort');
      if (sortSelect) sortSelect.value = state.sort;
    }
    markActiveChips();
    markTypeBtns();
    updateActiveFiltersLabel();
  })();

  // Expose for debugging
  window.catalogState = state;
  window.catalogReload = loadAndReplace;

})();
</script>

<!-- Стили -->
<style>
/* Micro animations and styles */
.card-item { transition: transform .25s ease, box-shadow .25s ease; }
.card-item:hover{ transform: translateY(-6px) scale(1.01); box-shadow: 0 14px 40px rgba(8,66,60,0.08); }
@keyframes bounce{ 0%,100%{ transform: translateY(0); } 50%{ transform: translateY(-6px); } }
.animate-bounce{ animation: bounce .6s; }
.animate-pulse { animation: pulse 1.2s infinite; }
@keyframes pulse { 0%{opacity:1} 50%{opacity:.5} 100%{opacity:1} }

.category-chip { transition: transform .12s, background .12s, color .12s, box-shadow .12s; cursor: pointer; }
.category-chip.active, .category-chip.bg-[#118568] { background: #118568; color: #313231 !important; box-shadow: 0 6px 18px rgba(17,133,104,0.12); }
.category-chip:focus { outline: none; box-shadow: 0 6px 18px rgba(17,133,104,0.12); }
.no-scrollbar::-webkit-scrollbar { display: none; } /* hide horizontal scrollbar for chips */
</style>

<?php include_once __DIR__ . '/includes/footer.php'; ?>
