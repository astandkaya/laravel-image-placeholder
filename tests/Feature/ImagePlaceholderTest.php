<?php

use Illuminate\Support\Str;

it('固定サイズのPNGを返す', function () {
    $res = $this->get('/placeholder/300x300');

    $res->assertOk();
    $res->assertHeader('Content-Type', 'image/png');

    // PNG シグネチャ確認
    $body = $res->streamedContent();
    expect(substr($body, 0, 8))->toBe("\x89PNG\x0D\x0A\x1A\x0A");
});

it('jpg 指定で JPEG を返す', function () {
    $res = $this->get('/placeholder/640x360/ffcc00/333/jpg?text=Hello');

    $res->assertOk();
    $res->assertHeader('Content-Type', 'image/jpeg');

    // JPEG シグネチャ確認 (FFD8)
    $body = $res->streamedContent();
    expect(substr($body, 0, 2))->toBe("\xFF\xD8");
});

it('許可外フォーマットは既定にフォールバックする', function () {
    // gif は許可外 → default(format=png) に戻る
    $res = $this->get('/placeholder/300x300?format=gif');

    $res->assertOk();
    $res->assertHeader('Content-Type', 'image/png');
});

it('ETag が付与され、If-None-Match で 304 を返す', function () {
    $first = $this->get('/placeholder/320x240');
    $first->assertOk();
    $etag = trim($first->headers->get('ETag'), '"');

    // 同じ条件で If-None-Match を付与
    $second = $this->get('/placeholder/320x240', ['If-None-Match' => "\"{$etag}\""]);
    $second->assertStatus(304);
});

it('meta=1 で JSON メタ情報を返す', function () {
    $res = $this->get('/placeholder/300x300?meta=1');

    $res->assertOk()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonStructure([
            'width', 'height', 'bg', 'fg', 'text', 'format', 'cache_key', 'random'
        ]);

    $json = $res->json();
    expect($json['width'])->toBe(300);
    expect($json['height'])->toBe(300);
    expect($json['random'])->toBeFalse();
});

it('nxn はランダムサイズだが seed で決定的に生成される', function () {
    // 乱数でも seed 固定なら同じ画像バイナリになる前提
    $res1 = $this->get('/placeholder/nxn?seed=abc123');
    $res2 = $this->get('/placeholder/nxn?seed=abc123');

    $res1->assertOk();
    $res2->assertOk();

    expect($res1->getContent())->toBe($res2->getContent());
});

it('ランダムは既定で no-store ヘッダになる', function () {
    $res = $this->get('/placeholder/nxn');

    $res->assertOk();
    $res->assertHeader('Cache-Control', 'max-age=0, must-revalidate, no-cache, no-store, private');
});

it('サイズ上限設定が効く（過大サイズは丸められる）', function () {
    config()->set('image-placeholder.max_width', 1024);
    config()->set('image-placeholder.max_height', 1024);

    // meta=1 で返る width/height が丸められていることを確認
    $res = $this->get('/placeholder/99999x99999?meta=1');

    $res->assertOk();
    $json = $res->json();
    expect($json['width'])->toBe(1024);
    expect($json['height'])->toBe(1024);
});

it('ディスクキャッシュに保存され、以降はファイルから返せる', function () {
    $tmp = sys_get_temp_dir() . '/imgph-cache-' . bin2hex(random_bytes(4));
    @mkdir($tmp, 0777, true);

    config()->set('image-placeholder.cache.disk', true);
    config()->set('image-placeholder.cache.disk_path', $tmp);

    // 1 回目生成
    $res1 = $this->get('/placeholder/400x300');
    $res1->assertOk();
    $etag = trim($res1->headers->get('ETag'), '"');
    $path = "{$tmp}/{$etag}.png";
    expect(is_file($path))->toBeTrue();

    // 2 回目は fileResponse が使われても同じヘッダと内容で返る
    $res2 = $this->get('/placeholder/400x300');
    $res2->assertOk();
    $res2->assertHeader('Content-Type', 'image/png');

    // クリーンアップ
    @unlink($path);
    @rmdir($tmp);
});

it('prefix を変更してもルートが機能する', function () {
    putenv('PLACEHOLDER_PREFIX=img');

    // アプリケーションをリフレッシュしてルーティングの設定を反映させる
    $this->refreshApplication();

    // Route は ServiceProvider の boot 時に組み立てられるので、
    // 変更後のリクエストが通るかを確認（Testbench は都度再解決する）
    $res = $this->get('/img/300x300');
    $res->assertOk();
});