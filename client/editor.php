<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/security.php';
init_secure_session();

// Доступ только авторизованным пользователям с флагом can_use_editor
if (!is_authenticated()) {
    header('Location: /login.php');
    exit;
}

$stmt = $pdo->prepare('SELECT can_use_editor FROM users WHERE id = ?');
$stmt->execute([$_SESSION['user_id']]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row || (int) $row['can_use_editor'] !== 1) {
    http_response_code(403);
    echo 'Доступ к редактору выключен. Обратитесь к администратору.';
    exit;
}

$errors = [];
$preview_svg = null;
$text_bg_png_path = null; // PNG фон+текст без фото
$final_png_path = null;   // Итоговый PNG с фото

// AJAX без перезагрузки: обработка генерации текст+фон и экспорта финала
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    verify_csrf();
    header('Content-Type: application/json; charset=utf-8');
    $action = $_POST['ajax'];
    $full_name = sanitize_text($_POST['full_name'] ?? '', 100);
    $subtitle = sanitize_text($_POST['subtitle'] ?? '', 200);
    $title = sanitize_text($_POST['title'] ?? 'С днем рождения!', 200);
    $name_font_size = (int) ($_POST['name_font_size'] ?? 266);
    $photo_full_path = $_POST['photo_full_path'] ?? null;
    $photo_url = sanitize_text($_POST['photo_url'] ?? '', 255);
    $resp = ['success' => false];
    try {
        if ($action === 'textbg') {
            $infoSvgPath = __DIR__ . '/../assets/images/info.svg';
            if (!file_exists($infoSvgPath))
                throw new Exception('Шаблон info.svg не найден');
            $svg = file_get_contents($infoSvgPath);
            $svg = str_replace('Имя отчество', e($full_name), $svg);
            $svg = str_replace('текст', e($subtitle), $svg);
            $svg = str_replace('С днем рождения!', e($title), $svg);
            $svg = preg_replace('/(\.fnt1\s*\{[^}]*font-size:)\s*([\d\.]+)px/i', '$1 ' . $name_font_size . 'px', $svg, 1);
            $svg = preg_replace('/(\.fil0\s*\{[^}]*fill:)\s*#[0-9A-Fa-f]{6}/', '$1 transparent', $svg, 1);
            if (!class_exists('Imagick'))
                throw new Exception('Imagick недоступен');
            $textLayer = new Imagick();
            $textLayer->readImageBlob($svg);
            $textLayer->setImageFormat('png');
            $bgDisk = __DIR__ . '/../assets/images/background.jpg';
            if (!file_exists($bgDisk))
                throw new Exception('Фон background.jpg не найден');
            $bg = new Imagick($bgDisk);
            $bg->setImageFormat('png');
            $bg->resizeImage(3508, 2480, Imagick::FILTER_LANCZOS, 1);
            $bg->compositeImage($textLayer, Imagick::COMPOSITE_OVER, 0, 0);
            $pngName = 'editor_textbg_' . uniqid() . '.png';
            $pngPath = __DIR__ . '/../storage/uploads/' . $pngName;
            $bg->writeImage($pngPath);
            $resp = ['success' => true, 'text_bg_png_path' => '/storage/uploads/' . $pngName];
        } elseif ($action === 'export') {
            if (!class_exists('Imagick'))
                throw new Exception('Imagick недоступен');
            $textBgUrl = sanitize_text($_POST['text_bg_png_path'] ?? '', 255);
            $textBgDisk = __DIR__ . '/..' . $textBgUrl;
            if (!file_exists($textBgDisk))
                throw new Exception('Основа не найдена');
            if (!$photo_full_path || !file_exists($photo_full_path))
                throw new Exception('Фото не найдено');
            $base = new Imagick($textBgDisk);
            $base->setImageFormat('png');
            $photo = new Imagick($photo_full_path);
            $pixClass = 'ImagickPixel';
            $drawClass = 'ImagickDraw';
            $photo->setImageBackgroundColor(new $pixClass('transparent'));
            $scale = floatval($_POST['tx_scale'] ?? '1');
            $rot = floatval($_POST['tx_rotation'] ?? '0');
            $offX = intval($_POST['tx_offset_x'] ?? '0');
            $offY = intval($_POST['tx_offset_y'] ?? '0');
            $w = max(1, (int) round($photo->getImageWidth() * $scale));
            $h = max(1, (int) round($photo->getImageHeight() * $scale));
            $photo->resizeImage($w, $h, Imagick::FILTER_LANCZOS, 1);
            $photo->rotateImage(new $pixClass('transparent'), rad2deg($rot));
            $layer = new Imagick();
            $layer->newImage(3508, 2480, new $pixClass('transparent'), 'png');
            $cx = (239.1 + 1551.98 + 1551.98 + 239.1) / 4.0;
            $cy = (2148.57 + 1843.03 + 294.89 + 598.51) / 4.0;
            $px = (int) round($cx + $offX - $photo->getImageWidth() / 2);
            $py = (int) round($cy + $offY - $photo->getImageHeight() / 2);
            $layer->compositeImage($photo, Imagick::COMPOSITE_OVER, $px, $py);
            $mask = new Imagick();
            $mask->newImage(3508, 2480, new $pixClass('black'));
            $draw = new $drawClass();
            $draw->setFillColor(new $pixClass('white'));
            $draw->setStrokeAntialias(true);
            $draw->polygon([
                ['x' => 239.1, 'y' => 2148.57],
                ['x' => 1551.98, 'y' => 1843.03],
                ['x' => 1551.98, 'y' => 294.89],
                ['x' => 239.1, 'y' => 598.51],
            ]);
            $mask->drawImage($draw);
            if (method_exists($layer, 'setImageAlphaChannel')) {
                $layer->setImageAlphaChannel(Imagick::ALPHACHANNEL_SET);
            }
            $layer->compositeImage($mask, Imagick::COMPOSITE_DSTIN, 0, 0);
            $base->compositeImage($layer, Imagick::COMPOSITE_OVER, 0, 0);
            $outName = 'editor_final_' . uniqid() . '.png';
            $outPath = __DIR__ . '/../storage/uploads/' . $outName;
            $base->writeImage($outPath);
            $resp = ['success' => true, 'final_png_path' => '/storage/uploads/' . $outName];
        } else {
            throw new Exception('Неизвестное действие');
        }
    } catch (Exception $ex) {
        $resp = ['success' => false, 'error' => $ex->getMessage()];
    }
    echo json_encode($resp);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['editor_submit']) || isset($_POST['export_final']))) {
    verify_csrf();

    $full_name = sanitize_text($_POST['full_name'] ?? '', 100);
    $subtitle = sanitize_text($_POST['subtitle'] ?? '', 200);
    $title = sanitize_text($_POST['title'] ?? 'С днем рождения!', 200);
    $name_font_size = (int) ($_POST['name_font_size'] ?? 266);

    if ($full_name === '')
        $errors[] = 'Введите имя и отчество';

    $photo_url = null;
    $photo_full_path = $_POST['photo_full_path'] ?? null;
    if (!empty($_FILES['photo']) && is_uploaded_file($_FILES['photo']['tmp_name'])) {
        $upload = secure_file_upload($_FILES['photo'], [
            'allowed_types' => ['image/jpeg', 'image/png'],
            'allowed_extensions' => ['jpg', 'jpeg', 'png'],
            'upload_dir' => __DIR__ . '/../storage/uploads/',
            'public_dir' => '/storage/uploads/',
            'filename_prefix' => 'editor_'
        ]);
        if ($upload['success']) {
            $photo_url = $upload['public_url'];
            $photo_full_path = $upload['full_path'];
        } else {
            $errors[] = $upload['error'] ?? 'Ошибка загрузки фото';
        }
    }
    if (!$photo_url) {
        $photo_url = sanitize_text($_POST['photo_url'] ?? '', 255);
    }
    if (!$photo_full_path) {
        $errors[] = 'Загрузите фотографию';
    }

    if (!$errors) {
        // Построим SVG фон+текст (без фото): используем info.svg, заменим тексты и размер шрифта имени
        $infoSvgPath = __DIR__ . '/../assets/images/info.svg';
        if (!file_exists($infoSvgPath)) {
            $errors[] = 'Шаблон info.svg не найден';
        } else {
            $svg = file_get_contents($infoSvgPath);
            // Тексты
            $svg = str_replace('Имя отчество', e($full_name), $svg);
            $svg = str_replace('текст', e($subtitle), $svg);
            $svg = str_replace('С днем рождения!', e($title), $svg);
            // Размер шрифта имени — обновляем .fnt1
            $svg = preg_replace('/(\.fnt1\s*\{[^}]*font-size:)\s*([\d\.]+)px/i', '$1 ' . $name_font_size . 'px', $svg, 1);
            // Сделаем полигон прозрачным
            $svg = preg_replace('/(\.fil0\s*\{[^}]*fill:)\s*#[0-9A-Fa-f]{6}/', '$1 transparent', $svg, 1);
            // Не вставляем фон в SVG: рендерим текстовый слой прозрачным PNG и компонуем его на фоне через Imagick
            $preview_svg = $svg;

            if (class_exists('Imagick')) {
                try {
                    // 1) Рендер прозрачного PNG с текстами из SVG
                    $textLayer = new Imagick();
                    $textLayer->readImageBlob($svg);
                    $textLayer->setImageFormat('png');
                    // 2) Загрузим фон и совместим
                    $bgDisk = __DIR__ . '/../assets/images/background.jpg';
                    if (!file_exists($bgDisk))
                        throw new Exception('Фон background.jpg не найден');
                    $bg = new Imagick($bgDisk);
                    $bg->setImageFormat('png');
                    // Убедимся в одинаковом размере
                    $bg->resizeImage(3508, 2480, Imagick::FILTER_LANCZOS, 1);
                    // Наложим текстовый слой поверх фона
                    $bg->compositeImage($textLayer, Imagick::COMPOSITE_OVER, 0, 0);
                    $pngName = 'editor_textbg_' . uniqid() . '.png';
                    $pngPath = __DIR__ . '/../storage/uploads/' . $pngName;
                    $bg->writeImage($pngPath);
                    $text_bg_png_path = '/storage/uploads/' . $pngName;
                } catch (Exception $e) {
                    $errors[] = 'Не удалось создать текстовый PNG: ' . $e->getMessage();
                }
            } else {
                $errors[] = 'Imagick недоступен — генерация PNG невозможна';
            }
        }
    }

    // Экспорт финального PNG
    if (!$errors && isset($_POST['export_final'])) {
        if (!class_exists('Imagick')) {
            $errors[] = 'Imagick недоступен — экспорт невозможен';
        } else {
            try {
                $svgW = 3508;
                $svgH = 2480;
                $textBgUrl = $text_bg_png_path ?: ($_POST['text_bg_png_path'] ?? '');
                $textBgDisk = __DIR__ . '/..' . $textBgUrl;
                if (!file_exists($textBgDisk))
                    throw new Exception('Основа не найдена');

                $base = new Imagick($textBgDisk);
                $base->setImageFormat('png');

                $photo = new Imagick($photo_full_path);
                $pixClass = 'ImagickPixel';
                $drawClass = 'ImagickDraw';
                $photo->setImageBackgroundColor(new $pixClass('transparent'));
                $scale = floatval($_POST['tx_scale'] ?? '1');
                $rot = floatval($_POST['tx_rotation'] ?? '0');
                $offX = intval($_POST['tx_offset_x'] ?? '0');
                $offY = intval($_POST['tx_offset_y'] ?? '0');

                $w = max(1, (int) round($photo->getImageWidth() * $scale));
                $h = max(1, (int) round($photo->getImageHeight() * $scale));
                $photo->resizeImage($w, $h, Imagick::FILTER_LANCZOS, 1);
                $photo->rotateImage(new $pixClass('transparent'), rad2deg($rot));

                $layer = new Imagick();
                $layer->newImage($svgW, $svgH, new $pixClass('transparent'), 'png');
                $cx = (239.1 + 1551.98 + 1551.98 + 239.1) / 4.0;
                $cy = (2148.57 + 1843.03 + 294.89 + 598.51) / 4.0;
                $px = (int) round($cx + $offX - $photo->getImageWidth() / 2);
                $py = (int) round($cy + $offY - $photo->getImageHeight() / 2);
                $layer->compositeImage($photo, Imagick::COMPOSITE_OVER, $px, $py);

                // Строим альфа-маску полигона: белое = видно, чёрное = прозрачно
                $mask = new Imagick();
                $mask->newImage($svgW, $svgH, new $pixClass('black'), 'png');
                $draw = new $drawClass();
                $draw->setFillColor(new $pixClass('white'));
                $draw->setStrokeAntialias(true);
                $draw->polygon([
                    ['x' => 239.1, 'y' => 2148.57],
                    ['x' => 1551.98, 'y' => 1843.03],
                    ['x' => 1551.98, 'y' => 294.89],
                    ['x' => 239.1, 'y' => 598.51],
                ]);
                $mask->drawImage($draw);
                // Копируем альфу из маски в слой фото
                if (method_exists($layer, 'setImageAlphaChannel')) {
                    $layer->setImageAlphaChannel(Imagick::ALPHACHANNEL_SET);
                }
                $layer->compositeImage($mask, Imagick::COMPOSITE_COPYOPACITY, 0, 0);

                $base->compositeImage($layer, Imagick::COMPOSITE_OVER, 0, 0);
                $outName = 'editor_final_' . uniqid() . '.png';
                $outPath = __DIR__ . '/../storage/uploads/' . $outName;
                $base->writeImage($outPath);
                $final_png_path = '/storage/uploads/' . $outName;
            } catch (Exception $e) {
                $errors[] = 'Экспорт не удался: ' . $e->getMessage();
            }
        }
    }
}

?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Редактор открытки</title>
    <link rel="stylesheet" href="/assets/css/tailwind.css">
    <style>
        @font-face {
            font-family: 'Bebas Neue Bold';
            src: url('/assets/font/BebasNeue Bold.otf') format('opentype');
            font-weight: bold;
            font-style: normal;
        }
    </style>
</head>

<body class="bg-gray-50">
    <?php include __DIR__ . '/../includes/header.php'; ?>
    <div class="max-w-6xl mx-auto p-6">
        <h1 class="text-2xl font-semibold mb-4">Редактор текста и фото</h1>
        <?php if ($errors): ?>
            <div class="bg-red-50 text-red-700 p-4 rounded mb-4">
                <?php foreach ($errors as $err)
                    echo '<div>' . e($err) . '</div>'; ?>
            </div>
        <?php endif; ?>
        <form method="post" enctype="multipart/form-data"
            class="grid grid-cols-1 md:grid-cols-3 gap-6 bg-white p-6 rounded shadow">
            <div class="md:col-span-1 space-y-4">
                <label class="block">
                    <span class="text-sm">Имя и отчество</span>
                    <input type="text" name="full_name" class="mt-1 w-full border rounded p-2"
                        value="<?php echo e($_POST['full_name'] ?? ''); ?>" required>
                </label>
                <label class="block">
                    <span class="text-sm">Заголовок</span>
                    <input type="text" name="title" class="mt-1 w-full border rounded p-2"
                        value="<?php echo e($_POST['title'] ?? 'С днем рождения!'); ?>">
                </label>
                <label class="block">
                    <span class="text-sm">Подстрочный текст</span>
                    <input type="text" name="subtitle" class="mt-1 w-full border rounded p-2"
                        value="<?php echo e($_POST['subtitle'] ?? ''); ?>">
                </label>
                <label class="block">
                    <span class="text-sm">Фотография (JPEG/PNG)</span>
                    <input type="file" name="photo" accept="image/jpeg,image/png" class="mt-1 w-full">
                </label>
                <div class="grid grid-cols-3 gap-2">
                    <label class="block"><span class="text-sm">Смещение X</span><input type="number" step="1"
                            name="offset_x" class="mt-1 w-full border rounded p-2"
                            value="<?php echo e($_POST['offset_x'] ?? '0'); ?>"></label>
                    <label class="block"><span class="text-sm">Смещение Y</span><input type="number" step="1"
                            name="offset_y" class="mt-1 w-full border rounded p-2"
                            value="<?php echo e($_POST['offset_y'] ?? '0'); ?>"></label>
                    <label class="block"><span class="text-sm">Масштаб</span><input type="number" step="0.01"
                            name="scale" class="mt-1 w-full border rounded p-2"
                            value="<?php echo e($_POST['scale'] ?? '1'); ?>"></label>
                </div>
                <label class="block mt-2">
                    <span class="text-sm">Размер шрифта (Имя и Отчество)</span>
                    <input type="range" min="120" max="320" step="2" id="nameFontSize" class="w-full"
                        value="<?php echo e($_POST['name_font_size'] ?? '266'); ?>">
                    <input type="hidden" name="name_font_size" id="nameFontSizeHidden"
                        value="<?php echo e($_POST['name_font_size'] ?? '266'); ?>">
                </label>

                <input type="hidden" name="photo_url" value="<?php echo e($photo_url ?? ''); ?>">
                <input type="hidden" name="photo_full_path" value="<?php echo e($photo_full_path ?? ''); ?>">
                <input type="hidden" name="text_bg_png_path" value="<?php echo e($text_bg_png_path ?? ''); ?>">
                <input type="hidden" name="tx_offset_x" id="tx_offset_x" value="0">
                <input type="hidden" name="tx_offset_y" id="tx_offset_y" value="0">
                <input type="hidden" name="tx_scale" id="tx_scale" value="1">
                <input type="hidden" name="tx_rotation" id="tx_rotation" value="0">
                <?php echo csrf_field(); ?>
                <div class="flex gap-3">
                    <button type="submit" name="editor_submit" class="px-4 py-2 bg-blue-600 text-white rounded">Обновить
                        превью</button>
                    <button type="submit" name="export_final" id="exportFinalBtn"
                        class="px-4 py-2 bg-green-600 text-white rounded">Скачать финальный файл</button>
                </div>
            </div>
            <div class="md:col-span-2">
                <div class="border rounded p-4 bg-gray-100">
                    <h3 class="text-sm font-semibold mb-2">Интерактивное превью (three.js)</h3>
                    <div id="three-preview" class="w-full max-w-[750px] h-[530px] bg-black/5 border rounded mx-auto">
                    </div>
                    <div class="text-xs text-gray-500 mt-3">ЛКМ — перемещение фото; колесо — масштаб; Shift+ЛКМ —
                        поворот. Полигон фиксирован и не двигается. Тексты и наклоны — как в макете.</div>
                </div>
                <?php if ($final_png_path): ?>
                    <div class="mt-4">
                        <a href="<?php echo e($final_png_path); ?>" class="px-4 py-2 bg-green-600 text-white rounded"
                            download>Скачать итоговый PNG</a>
                    </div>
                <?php endif; ?>
            </div>
        </form>
    </div>
    <?php include __DIR__ . '/../includes/footer.php'; ?>

    <?php if (!empty($photo_url)): ?>
        <script>
            (function () {
                // Canvas-превью вместо three.js: фон, тексты с матрицами, фото в клипе
                const sizeInput = document.getElementById('nameFontSize');
                const sizeHidden = document.getElementById('nameFontSizeHidden');
                if (sizeInput && sizeHidden) sizeInput.addEventListener('input', () => sizeHidden.value = sizeInput.value);
                const container = document.getElementById('three-preview');
                let previewW = container.clientWidth;
                let previewH = container.clientHeight;
                const cvs = document.createElement('canvas');
                container.innerHTML = '';
                container.appendChild(cvs);
                const ctx = cvs.getContext('2d');

                const fullW = 3508, fullH = 2480.4;
                let sx = 1, sy = 1;
                function recalcScale() {
                    previewW = container.clientWidth; previewH = container.clientHeight;
                    cvs.width = previewW; cvs.height = previewH;
                    const scalePreview = Math.min(previewW / fullW, previewH / fullH);
                    sx = scalePreview; sy = scalePreview;
                }
                recalcScale();

                const bgImg = new Image(); bgImg.src = '/assets/images/background.jpg';
                const photoImg = new Image(); photoImg.src = '<?php echo e($photo_url); ?>';

                let offsetX = 0, offsetY = 0, photoScale = 1, photoRot = 0;

                const poly = [
                    { x: 239.1, y: 2148.57 },
                    { x: 1551.98, y: 1843.03 },
                    { x: 1551.98, y: 294.89 },
                    { x: 239.1, y: 598.51 }
                ];
                function polyCenter() { let cx = 0, cy = 0; for (const p of poly) { cx += p.x; cy += p.y; } return { x: cx / 4, y: cy / 4 }; }

                function drawTextBlocks() {
                    // Смещаем тексты чуть выше, чтобы соответствовать оригиналу
                    const upTitle = 115;   // пиксели вверх для заголовка
                    const upName = 105;     // пиксели вверх для имени
                    const upSub = 40;      // пиксели вверх для подстрочного
                    // ВАЖНО: после setTransform координаты fillText должны быть исходными из SVG (без доп. масштабирования)
                    // Заголовок
                    ctx.save(); ctx.setTransform(1.09478 * sx, -0.268557 * sx, -0.0773969 * sy, 1.04857 * sy, 1636.34 * sx, (-263.246 - upTitle) * sy);
                    ctx.fillStyle = '#E31E24'; ctx.font = '269.87px "Bebas Neue Bold"'; ctx.textBaseline = 'top'; ctx.textAlign = 'left';
                    ctx.fillText('<?php echo e($title); ?>', 81.98, 1240.2 - upTitle); ctx.restore();
                    // Имя
                    ctx.save(); ctx.setTransform(0.991015 * sx, -0.237991 * sx, -0.0663663 * sy, 1.02388 * sy, -25.3858 * sx, (411.471 - upName) * sy);
                    ctx.fillStyle = '#FFFFFF'; ctx.font = (parseInt(sizeHidden.value, 10) || 266) + 'px "Bebas Neue Bold"'; ctx.textBaseline = 'top'; ctx.textAlign = 'left';
                    ctx.fillText('<?php echo e($full_name); ?>', 1754, 1240.2 - upName); ctx.restore();
                    // Подстрочный
                    ctx.save(); ctx.setTransform(1.04747 * sx, -0.237991 * sx, -0.0776956 * sy, 0.971267 * sy, -101.443 * sx, (614.397 - upSub) * sy);
                    ctx.fillStyle = '#FFFFFF'; ctx.font = '99.95px "Bebas Neue Bold"'; ctx.textBaseline = 'top'; ctx.textAlign = 'left';
                    ctx.fillText('<?php echo e($subtitle); ?>', 1754, 1240.2 - upSub); ctx.restore();
                }

                function drawAll() {
                    ctx.clearRect(0, 0, previewW, previewH);
                    // фон
                    ctx.save(); ctx.setTransform(sx, 0, 0, sy, 0, 0); ctx.drawImage(bgImg, 0, 0, fullW, fullH); ctx.restore();
                    // тексты
                    drawTextBlocks();
                    // фото внутри полигона
                    const c = polyCenter();
                    ctx.save(); ctx.setTransform(sx, 0, 0, sy, 0, 0);
                    ctx.beginPath(); ctx.moveTo(poly[0].x, poly[0].y); for (let i = 1; i < poly.length; i++) { ctx.lineTo(poly[i].x, poly[i].y); } ctx.closePath(); ctx.clip();
                    ctx.translate(c.x + offsetX, c.y + offsetY); ctx.rotate(photoRot);
                    const iw = photoImg.naturalWidth || photoImg.width; const ih = photoImg.naturalHeight || photoImg.height;
                    ctx.scale(photoScale, photoScale);
                    if (iw && ih) ctx.drawImage(photoImg, -iw / 2, -ih / 2);
                    ctx.restore();
                }

                function ready() { if (bgImg.complete && photoImg.complete) { recalcScale(); drawAll(); } }
                bgImg.onload = ready; photoImg.onload = ready; if (bgImg.complete && photoImg.complete) ready();

                // Управление фото
                let isDrag = false, lastX = 0, lastY = 0, shift = false;
                cvs.addEventListener('mousedown', (e) => {
                    const rect = cvs.getBoundingClientRect();
                    isDrag = true;
                    lastX = (e.clientX - rect.left);
                    lastY = (e.clientY - rect.top);
                    shift = e.shiftKey;
                    e.preventDefault();
                });
                window.addEventListener('mouseup', () => { isDrag = false; });
                cvs.addEventListener('mouseleave', () => { isDrag = false; });
                cvs.addEventListener('mousemove', (e) => { if (!isDrag) return; const rect = cvs.getBoundingClientRect(); const dx = (e.clientX - rect.left) - lastX; const dy = (e.clientY - rect.top) - lastY; lastX = (e.clientX - rect.left); lastY = (e.clientY - rect.top); if (shift) { photoRot += dx * 0.005; } else { offsetX += dx / sx; offsetY += dy / sy; } drawAll(); });
                cvs.addEventListener('wheel', (e) => { e.preventDefault(); const s = (e.deltaY < 0 ? 1.05 : 0.95); photoScale *= s; drawAll(); });
                sizeInput?.addEventListener('input', () => { drawAll(); });

                // Респонсив: пересчёт при изменении размера окна/контейнера
                window.addEventListener('resize', () => { recalcScale(); drawAll(); });

                // Экспорт: полноразмерный Canvas 3508x2480, сохранение PNG
                const exportBtn = document.getElementById('exportFinalBtn');
                exportBtn?.addEventListener('click', (e) => {
                    e.preventDefault();
                    const out = document.createElement('canvas'); out.width = Math.round(fullW); out.height = Math.round(fullH);
                    const octx = out.getContext('2d');
                    // фон
                    octx.drawImage(bgImg, 0, 0, fullW, fullH);
                    // тексты в полном масштабе
                    // Смещаем тексты выше и при экспорте
                    const upTitle = 120, upName = 80, upSub = 60;
                    // Заголовок
                    octx.save(); octx.setTransform(1.09478, -0.268557, -0.0773969, 1.04857, 1636.34, -263.246 - upTitle); octx.fillStyle = '#E31E24'; octx.font = '269.87px "Bebas Neue Bold"'; octx.textBaseline = 'top'; octx.textAlign = 'left'; octx.fillText('<?php echo e($title); ?>', 81.98, 1240.2 - upTitle); octx.restore();
                    // Имя
                    octx.save(); octx.setTransform(0.991015, -0.237991, -0.0663663, 1.02388, -25.3858, 411.471 - upName); octx.fillStyle = '#FFFFFF'; octx.font = (parseInt(sizeHidden.value, 10) || 266) + 'px "Bebas Neue Bold"'; octx.textBaseline = 'top'; octx.textAlign = 'left'; octx.fillText('<?php echo e($full_name); ?>', 1754, 1240.2 - upName); octx.restore();
                    // Подстрочный
                    octx.save(); octx.setTransform(1.04747, -0.237991, -0.0776956, 0.971267, -101.443, 614.397 - upSub); octx.fillStyle = '#FFFFFF'; octx.font = '99.95px "Bebas Neue Bold"'; octx.textBaseline = 'top'; octx.textAlign = 'left'; octx.fillText('<?php echo e($subtitle); ?>', 1754, 1240.2 - upSub); octx.restore();
                    // фото внутри полигона
                    const c = polyCenter();
                    octx.save(); octx.beginPath(); octx.moveTo(poly[0].x, poly[0].y); for (let i = 1; i < poly.length; i++) { octx.lineTo(poly[i].x, poly[i].y); } octx.closePath(); octx.clip();
                    octx.translate(c.x + offsetX, c.y + offsetY); octx.rotate(photoRot);
                    const iw = photoImg.naturalWidth || photoImg.width; const ih = photoImg.naturalHeight || photoImg.height;
                    octx.scale(photoScale, photoScale); if (iw && ih) octx.drawImage(photoImg, -iw / 2, -ih / 2); octx.restore();
                    out.toBlob(function (blob) { const url = URL.createObjectURL(blob); const a = document.createElement('a'); a.href = url; a.download = 'final.png'; a.click(); URL.revokeObjectURL(url); }, 'image/png');
                });
            })();
        </script>
    <?php endif; ?>
</body>

</html>