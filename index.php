<?php

// ==========  設定自訂功能 ==========
$baseDir = '.';               // 基礎目錄
$displayFiles = true;         // 是否顯示非 MP3 檔案
$autoRedirectPages = true;    // 是否自動導向到 index.php / index.html


// ==========  取得資料夾檔案清單 ==========

// 取得目前資料夾路徑
$folder = $_GET['folder'] ?? '';
$currentPath = realpath("$baseDir/$folder");
$relativeFolder = trim(str_replace('\\', '/', ltrim(str_replace(realpath($baseDir), '', $currentPath), '/\\')), '/');

// 安全性檢查：防止路徑跳脫 base 目錄
if (!$currentPath || strpos($currentPath, realpath($baseDir)) !== 0) {
    http_response_code(403);
    exit("非法路徑");
}

// 掃描目錄內容
$items = scandir($currentPath);
$data = array_fill_keys(['folders', 'files', 'mp3'], []);

// 自動導向到 index.php / index.html 的資料夾
if ($autoRedirectPages && !empty($folder) && !empty(array_intersect(['index.php', 'index.html'], $items))) {
    header("Location: {$baseDir}/{$relativeFolder}/");
    exit();
}

// 遍歷目前資料夾中的所有項目
foreach ($items as $item) {
    if ($item === '.' || $item === '..') continue; // 忽略特殊目錄

    $encodedName = htmlspecialchars($item, ENT_QUOTES);
    $url = $relativeFolder ? "$relativeFolder/$item" : $item;
    $href = htmlspecialchars("$baseDir/$url", ENT_QUOTES);

    if (is_dir("{$currentPath}/{$item}")) {
        $data['folders'][] = "<a class=\"folder\" href=\"?folder=" . urlencode($url) . "\">📁 $encodedName</a>";
    } elseif (in_array(strtolower(pathinfo($item, PATHINFO_EXTENSION)), ['mp3', 'm4a'])) {
        $data['mp3'][] = $item;
    } elseif ($displayFiles && (!empty($folder) || $item !== 'index.php')) {
        $data['files'][] = "<a class=\"folder\" href=\"$href\" target=\"_blank\">📄 $encodedName</a>";
    }
}


// ========== 組合播放清單 HTML ==========

// 返回上一層連結
$playlistHtml = $relativeFolder ? '<a class="folder" href="?folder=' . urlencode((dirname($relativeFolder) === '.' ? '' : dirname($relativeFolder))) . '">🔙 返回上一層</a>' : '';

// 加入資料夾與文件連結
$playlistHtml .= implode('', $data['folders']) . implode('', $data['files']);

// 加入 MP3 播放項目
foreach ($data['mp3'] as $i => $item) {
    $playlistHtml .= '<div class="track" data-index="' . $i . '" data-src="' . htmlspecialchars($relativeFolder ? "{$baseDir}/{$relativeFolder}/{$item}" : "{$baseDir}/{$item}", ENT_QUOTES) . '">🎧 ' . htmlspecialchars($item, ENT_QUOTES) . '</div>';
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>MP3 播放器</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <style>
        /* 基礎設定 */
        html, body {
            touch-action: manipulation;
        }
        
        body {
            font-family: sans-serif;
            margin: 0;
            padding-bottom: 140px;
            background: #f0f0f0;
        }

        /* 列表項目樣式 */
        .track, .folder {
            background: #fff;
            padding: 1em;
            margin: 0.5em;
            border-radius: 8px;
            cursor: pointer;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .track.active {
            background-color: #d1e7dd;
            font-weight: bold;
        }

        a.folder {
            text-decoration: none;
            display: block;
            color: inherit;
        }

        /* 播放器固定區域 */
        .player-fixed {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: #fff;
            box-shadow: 0 -2px 10px rgba(0,0,0,0.1);
            padding: 1em;
            z-index: 100;
        }

        /* 控制按鈕區域 */
        .controls {
            display: flex;
            justify-content: space-between;
            margin: 0.5em 0;
        }

        .controls button {
            flex: 1;
            padding: 0.75em;
            margin: 0 0.25em;
            border: none;
            border-radius: 6px;
            background-color: #007bff;
            color: white;
            font-size: 1em;
        }

        .controls button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* 播放/暫停按鈕樣式 */
        #playPauseBtn { 
            flex: 1;
            transition: background-color 0.3s;
        }

        #playPauseBtn.playing {
            background-color: #6c757d;
        }

        #playPauseBtn.paused {
            background-color: green;
        }

        #playPauseBtn:disabled {
            background-color: #6c757d;
        }

        /* 上一首/下一首按鈕 */
        #prevBtn, #nextBtn {
            flex: 0.5;
            max-width: 150px;
        }

        /* 播放模式按鈕 */
        #modeBtn {
            background-color: #9966CC;
            color: white;
            max-width: 100px;
        }

        /* 播放器 */
        audio {
            width: 100%;
        }

    </style>
</head>
<body>
    <h2 style="padding: 1em;">📁 資料夾：<?= htmlspecialchars($relativeFolder ?: '/') ?></h2>

    <div id="playlist">
        <?= $playlistHtml ?>
    </div>

    <?php if (!empty($data['mp3'])): ?>
    <div class="player-fixed">
        <audio id="audio" controls></audio>
        <div class="controls">
            <button id="modeBtn">🔁 循環</button>
            <button id="prevBtn">⏮</button>
            <button id="playPauseBtn">▶ 播放</button>
            <button id="nextBtn">⏭</button>
        </div>
    </div>

    <script>
        // 初始化播放模式，從 localStorage 取得或預設為 'repeat'
        let playMode = localStorage.getItem('playMode') || 'repeat';

        // 取得音頻元素和控制按鈕
        const audio = document.getElementById('audio');
        const tracks = Array.from(document.querySelectorAll('.track'));
        const prevBtn = document.getElementById('prevBtn');
        const nextBtn = document.getElementById('nextBtn');
        const modeBtn = document.getElementById('modeBtn');
        const playPauseBtn = document.getElementById('playPauseBtn');

        let currentIndex = -1; // 設定當前播放索引

        // 更新播放模式按鈕的圖示和文字
        function updateModeIcon() {
            modeBtn.className = '';

            if (playMode === 'repeat') {
                modeBtn.textContent = '🔁 循環';
                modeBtn.classList.add('repeat');
            } else if (playMode === 'single') {
                modeBtn.textContent = '🔂 單曲';
                modeBtn.classList.add('single');
            } else if (playMode === 'random') {
                modeBtn.textContent = '🔀 隨機';
                modeBtn.classList.add('random');
            } else {
                modeBtn.textContent = '🚫 手動';
                modeBtn.classList.add('none');
            }
        }

        // 切換播放模式並更新按鈕圖示
        modeBtn.addEventListener('click', () => {
            playMode = playMode === 'repeat' ? 'single' :
                       playMode === 'single' ? 'random' :
                       playMode === 'random' ? 'none' : 'repeat';
            updateModeIcon();
            localStorage.setItem('playMode', playMode);
        });

        // 播放指定索引的音軌
        function playTrack(index) {
            if (index < 0 || index >= tracks.length) return;
            currentIndex = index;
            tracks.forEach(t => t.classList.remove('active'));
            const track = tracks[index];
            track.classList.add('active');
            audio.src = track.getAttribute('data-src');
            playPauseBtn.disabled = false; // 啟用播放按鈕
            audio.play();
        }

        // 取得下一個播放索引，根據播放模式決定
        function getNextIndex() {
            if (playMode === 'random') {
                let next;
                do {
                    next = Math.floor(Math.random() * tracks.length);
                } while (tracks.length > 1 && next === currentIndex);
                return next;
            } else {
                return (currentIndex + 1) % tracks.length;
            }
        }

        // 當音軌播放結束時，根據播放模式決定是否播放下一首
        audio.addEventListener('ended', () => {
            if (playMode === 'none') {
                return;
            } else if (playMode === 'single') {
                playTrack(currentIndex);
            } else {
                playTrack(getNextIndex());
            }
        });

        // 為每個音軌添加點擊事件，點擊時播放該音軌
        tracks.forEach((track, i) => {
            track.addEventListener('click', () => playTrack(i));
        });

        // 上一首按鈕點擊事件，播放上一首音軌
        prevBtn.addEventListener('click', () => {
            if (playMode === 'random') {
                playTrack(getNextIndex());
            } else {
                const prevIndex = (currentIndex - 1 + tracks.length) % tracks.length;
                playTrack(prevIndex);
            }
        });

        // 下一首按鈕點擊事件，播放下一首音軌
        nextBtn.addEventListener('click', () => {
            playTrack(getNextIndex());
        });

        // 播放/暫停按鈕點擊事件
        playPauseBtn.addEventListener('click', () => {
            if (audio.paused) {
                audio.play();
                playPauseBtn.classList.remove('paused');
                playPauseBtn.classList.add('playing');
                playPauseBtn.innerHTML = '❚❚ 暫停';
            } else {
                audio.pause();
                playPauseBtn.classList.remove('playing');
                playPauseBtn.classList.add('paused');
                playPauseBtn.innerHTML = '▶ 播放';
            }
        });

        // 初始化播放/暫停按鈕狀態
        playPauseBtn.classList.add('paused');
        playPauseBtn.innerHTML = '▶ 播放';
        playPauseBtn.disabled = true; // 初始化時禁用按鈕

        // 監聽音頻播放狀態變化
        audio.addEventListener('play', () => {
            playPauseBtn.classList.remove('paused');
            playPauseBtn.classList.add('playing');
            playPauseBtn.innerHTML = '❚❚ 暫停';
        });

        audio.addEventListener('pause', () => {
            playPauseBtn.classList.remove('playing');
            playPauseBtn.classList.add('paused');
            playPauseBtn.innerHTML = '▶ 播放';
        });

        // 初始化時更新播放模式按鈕的圖示
        updateModeIcon();
    </script>
    <?php endif ?>
</body>
</html>
