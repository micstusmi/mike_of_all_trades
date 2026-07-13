<?php

/**
 * Get the latest daily AUD → THB reference rate.
 *
 * Results are cached locally for six hours so every page request does
 * not contact the external API.
 */
function getAudToThbRate(): array
{
    $fallback = [
        'rate' => 22.50,
        'date' => date('Y-m-d'),
        'source' => 'fallback',
        'available' => false
    ];

    $cacheFile = sys_get_temp_dir()
        . '/thailand_trip_aud_thb_rate.json';

    $cacheLifetime = 6 * 60 * 60;

    if (
        is_file($cacheFile)
        && filemtime($cacheFile) >= time() - $cacheLifetime
    ) {
        $cached = json_decode(
            (string) file_get_contents($cacheFile),
            true
        );

        if (
            is_array($cached)
            && isset($cached['rate'])
            && (float) $cached['rate'] > 0
        ) {
            return $cached;
        }
    }

    $url =
        'https://api.frankfurter.dev/v1/latest'
        . '?base=AUD&symbols=THB';

    $response = false;

    if (function_exists('curl_init')) {
        $curl = curl_init($url);

        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_USERAGENT =>
                'MikeOfAllTrades-ThailandPlanner/1.0',
            CURLOPT_HTTPHEADER => [
                'Accept: application/json'
            ]
        ]);

        $response = curl_exec($curl);
        $status = (int) curl_getinfo(
            $curl,
            CURLINFO_HTTP_CODE
        );

        curl_close($curl);

        if ($status !== 200) {
            $response = false;
        }
    } elseif (filter_var(
        ini_get('allow_url_fopen'),
        FILTER_VALIDATE_BOOLEAN
    )) {
        $context = stream_context_create([
            'http' => [
                'timeout' => 8,
                'header' =>
                    "Accept: application/json\r\n"
                    . "User-Agent: "
                    . "MikeOfAllTrades-ThailandPlanner/1.0\r\n"
            ]
        ]);

        $response = @file_get_contents(
            $url,
            false,
            $context
        );
    }

    if ($response === false) {
        if (is_file($cacheFile)) {
            $oldCache = json_decode(
                (string) file_get_contents($cacheFile),
                true
            );

            if (
                is_array($oldCache)
                && isset($oldCache['rate'])
                && (float) $oldCache['rate'] > 0
            ) {
                $oldCache['available'] = false;
                $oldCache['source'] = 'cached';

                return $oldCache;
            }
        }

        return $fallback;
    }

    $data = json_decode($response, true);
    $rate = (float) ($data['rates']['THB'] ?? 0);

    if ($rate <= 0) {
        return $fallback;
    }

    $result = [
        'rate' => $rate,
        'date' => $data['date'] ?? date('Y-m-d'),
        'source' => 'frankfurter',
        'available' => true
    ];

    @file_put_contents(
        $cacheFile,
        json_encode(
            $result,
            JSON_UNESCAPED_SLASHES
        )
    );

    return $result;
}
