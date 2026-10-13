<?php
function link_filter($data) {
    $result = true;
    $link_filter_setting = Registry::load('settings')->link_filter;
    $links = isset($data['links']) && is_array($data['links']) ? $data['links'] : [];

    if ($link_filter_setting === 'enable' || $link_filter_setting === 'strict_mode') {
        $is_strict = ($link_filter_setting === 'strict_mode');
        $cache_file = $is_strict ? 'assets/cache/url_whitelist.cache' : 'assets/cache/url_blacklist.cache';
        $list_var = $is_strict ? 'url_whitelist' : 'url_blacklist';
        
        $$list_var = [];
        if (file_exists($cache_file)) {
            include($cache_file);
        }
        $current_list = is_array($$list_var) ? $$list_var : [];

        foreach ($links as $urlToCheck) {
            $urlToCheck = trim($urlToCheck);
            if (empty($urlToCheck)) continue;

            $userUrl = $urlToCheck;
            if (!preg_match('#^https?://#i', $userUrl)) {
                $userUrl = 'http://' . $userUrl;
            }
            $userHost = strtolower(parse_url($userUrl, PHP_URL_HOST));
            
            if (!$userHost) {
                return false;
            }

            $matched = false;

            foreach ($current_list as $entry) {
                $entry = trim(strtolower($entry));
                if (empty($entry)) continue;

                $entryClean = $entry;
                $entryClean = str_replace(['https://', 'http://'], '', $entryClean);
                $entryClean = rtrim($entryClean, '/*'); 
                $entryHost = $entryClean;

                if (strpos($entry, '*') !== false) {
                    $regex = str_replace('\*', '.*', preg_quote($entryHost, '/'));
                    if (preg_match('/^' . $regex . '$/i', $userHost)) {
                        $matched = true;
                    }
                } else {
                    if ($userHost === $entryHost || 
                        substr($userHost, -(strlen($entryHost) + 1)) === '.' . $entryHost) {
                        $matched = true;
                    }
                }

                if ($matched) break;
            }

            if (!$is_strict && $matched) {
                return false; 
            }
            if ($is_strict && !$matched) {
                return false; 
            }
        }
    }

    return $result;
}
?>