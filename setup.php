<?php
declare(strict_types=1);

$root = getcwd();
$cfile = $root . '/composer.json';
if (!file_exists($cfile)) {
    fwrite(STDERR, "composer.json not found\n");
    exit(1);
}

$raw = file_get_contents($cfile);
if (!preg_match('/t_pkg|t_ns|t_desc/', $raw)) {
    // 既に初期化済み
    exit(0);
}

// CI 等ではスキップしたい場合
if (getenv('CI') || getenv('SKIP_STARTER_SETUP')) {
    fwrite(STDOUT, "Skip starter setup (CI/SKIP env)\n");
    exit(0);
}

$data = json_decode($raw, true);

function ask(string $q, string $default = ''): string {
    $p = $default ? "$q [$default]: " : "$q: ";
    fwrite(STDOUT, $p);
    $line = fgets(STDIN);
    $line = $line === false ? '' : trim($line);
    return $line !== '' ? $line : $default;
}

$pkg = ask('Package name', '');
$desc = ask('Description', '');
$ns  = ask('Root namespace', '');

// 正規化
$pkg = strtolower($pkg);
$ns  = trim($ns, '\\');
$nsWithSlash = $ns . '\\';
$nsPath = str_replace('\\', '/', $ns);

// composer.json 更新
$data['name'] = "astandkaya/$pkg";
$data['description'] = $desc;
$data['autoload']['psr-4'] = [$nsWithSlash => "src/$nsPath"];
$data['autoload']['files'] = ["src/$nsPath/helpers.php"];
unset($data['scripts']['starter-setup']);
unset($data['scripts']['post-update-cmd']);

file_put_contents($cfile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);

// ディレクトリとファイルの作成
@mkdir("$root/src/$nsPath", 0777, true);
file_put_contents("$root/src/$nsPath/Example.php", <<<PHP
<?php

namespace {$nsWithSlash}\Example;

class Example {
    public static function hello(): string {
        return 'Hello, world!';
    }
}
PHP
);
file_put_contents("$root/src/$nsPath/helpers.php", <<<PHP
<?php

PHP
);

fwrite(STDOUT, "Starter setup complete.
- name: $pkg
- namespace: $nsWithSlash
- autoload path: src/$nsPath

Run: composer dump-autoload
");

// 最後に自分自身を削除する
unlink(__FILE__);

// ついでにREADME.mdも上書き
$readmeFile = $root . '/README.md';
file_put_contents($readmeFile, <<<MD
    # $pkg

    $desc

    ## Installation

    ```
    composer require astandkaya/$pkg
    ```

    ## Quick Start

    ...

    ## Usage

    ...

    MD);

// ついでにgit commitもしてしまう
exec('git add .');
exec('git commit -m ":tada: initial project setup"');
