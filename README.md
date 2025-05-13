# 🎵 PHP-MP3-Player

## 💡 特色
- **單檔運作**：僅需一個 `index.php` 檔案。
- **零依賴**：純 PHP + CSS + JavaScript，無需安裝套件。
- **快速部署**：複製即用，無需設定。

## ✨ 功能
- 📁 瀏覽資料夾與檔案。
- 🎵 支援 MP3 播放介面。
- 🔄 播放模式：循環、單曲、隨機、手動。
- 📱 支援手機版面。

## 🚀 部署方式
1. 將 `index.php` 複製到 PHP 網頁伺服器。
2. 完成。

## ⚙️ 快速自訂
在 `index.php` 開頭提供三個客製化設定：
```php
$baseDir = '.';               // 基礎目錄路徑
$displayFiles = true;         // 是否顯示非 MP3 的檔案
$autoRedirectPages = false;   // 是否自動導向到有 index.php 或 index.html 的資料夾
```

## 📦 系統需求
- PHP 5.4+
- 網頁伺服器（如 Apache、Nginx）。
