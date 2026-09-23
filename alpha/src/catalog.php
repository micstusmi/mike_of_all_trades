<?php
declare(strict_types=1);

function alpha_catalog(): array {
    $items = json_decode((string)file_get_contents(__DIR__.'/../catalog/features.json'),true,512,JSON_THROW_ON_ERROR);
    if (!is_array($items)) throw new RuntimeException('Feature catalogue unavailable.');
    return $items;
}

function alpha_catalog_html(array $items): string {
    $escape = static fn(string $s): string => htmlspecialchars($s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
    $html = '<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>ezTradie features</title>';
    $html .= '<style>body{font:16px system-ui,sans-serif;max-width:1000px;margin:auto;padding:24px;color:#172238;background:#f5f8fc}h1{font-size:2rem}p{line-height:1.5}.notice{background:#fff5d7;padding:16px;border-radius:12px}main{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:14px}.card{background:#fff;padding:18px;border:1px solid #d9e2ef;border-radius:14px}small{color:#526178}.tag{display:inline-block;padding:4px 9px;margin:3px 3px 0 0;background:#e6eef8;border-radius:30px}a{color:#155ea8}</style>';
    $html .= '<h1>ezTradie feature catalogue</h1><p class="notice">ezTradie alpha is under development. “Working on Mike’s site” describes the separate Mike of All Trades installation; it does not mean that feature is ready for ezTradie testers. There is no public signup yet.</p><main>';
    foreach ($items as $item) {
        $html .= '<article class="card"><small>'.$escape($item['id']).'</small><h2>'.$escape($item['title']).'</h2><p>'.$escape($item['description']).'</p>';
        $html .= '<span class="tag">ezTradie: '.$escape($item['alpha']).'</span><span class="tag">Mike site: '.$escape($item['legacy']).'</span></article>';
    }
    return $html.'</main><p><small>Availability and descriptions are reviewed before any sales presentation.</small></p></html>';
}
