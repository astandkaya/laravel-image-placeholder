# laravel-image-placeholder

A debug library for temporarily generating images.

## Installation

```
composer require astandkaya/laravel-image-placeholder
```

## Quick Start

- プロジェクトに導入
  - composer require your-vendor/laravel-placeholder
  - composer は intervention/image も自動で入ります
- 設定公開
  - php artisan vendor:publish --tag=image-placeholder-config
- ドライバ選択
  - .env に PLACEHOLDER_DRIVER=gd または imagick
  - Imagick を使う場合はサーバで ext-imagick を有効化
- アクセス例
  - 固定: /placeholder/300x300
  - ランダム: /placeholder/nxn
  - 色/書式: /placeholder/640x360/ffcc00/333/webp
  - クエリ: /placeholder/640x360?bg=ffcc00&fg=333&format=jpg&text=Hello
  - シード固定ランダム: /placeholder/nxn?seed=abc123
  - デバッグメタ: /placeholder/300x300?meta=1

## Usage

...
