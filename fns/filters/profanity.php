<?php

namespace Snipe\BanBuilder;

class CensorWords
{
    public array $badwords = [];
    private string $replacer = '*';
    private string $censorChecks = '';
    private array $whiteList = [];
    private string $whiteListPlaceHolder = ' {wl_%d} ';
    private ?array $leet_replace = null;

    public function __construct() {
        try {
            $this->addDictionary('blacklist');

            $whitelistPath = __DIR__ . '/fns/filters/whitelist.php';
            if (file_exists($whitelistPath)) {
                $whitelist = include($whitelistPath);
                if (is_array($whitelist)) {
                    $this->addWhiteList($whitelist);
                }
            }
        } catch (\Exception $e) {
            error_log("CensorWords Init Error: " . $e->getMessage());
        }
    }


    private function readBadWords($dictionary): array {
        $badwords = [];
        $baseDictPath = __DIR__ . DIRECTORY_SEPARATOR;

        $files = is_array($dictionary) ? $dictionary : [$dictionary];

        foreach ($files as $file) {
            $path = (file_exists($file)) ? $file : $baseDictPath . $file . '.php';

            if (file_exists($path)) {
                include $path;
            }
        }

        return array_unique($badwords);
    }

    public function addDictionary($dictionary): void {
        $newWords = $this->readBadWords($dictionary);
        $this->badwords = array_unique(array_merge($this->badwords, $newWords));
        $this->censorChecks = '';
    }

    public function addWhiteList(array $list): void {
        foreach ($list as $value) {
            if (is_string($value) && !empty($value)) {
                $this->whiteList[] = $value;
            }
        }
    }

    private function compileLeetDictionary(): void {
        if ($this->leet_replace !== null) return;

        $this->leet_replace = [
            'a' => '(a|a\.|a\-|@|Á|á|À|Â|à|Â|â|Ä|ä|Ã|ã|Å|å|α|Δ|Λ|λ)',
            'b' => '(b|b\.|b\-|8|\|3|ß|Β|β)',
            'c' => '(c|c\.|c\-|Ç|ç|¢|€|<|\(|{|©)',
            'd' => '(d|d\.|d\-|&part;|\|\)|Þ|þ|Ð|ð)',
            'e' => '(e|e\.|e\-|3|€|È|è|É|é|Ê|ê|∑)',
            'f' => '(f|f\.|f\-|ƒ)',
            'g' => '(g|g\.|g\-|6|9)',
            'h' => '(h|h\.|h\-|Η)',
            'i' => '(i|i\.|i\-|!|\||\]\[|]|1|∫|Ì|Í|Î|Ï|ì|í|î|ï)',
            'j' => '(j|j\.|j\-)',
            'k' => '(k|k\.|k\-|Κ|κ)',
            'l' => '(l|1\.|l\-|!|\||\]\[|]|£|∫|Ì|Í|Î|Ï)',
            'm' => '(m|m\.|m\-)',
            'n' => '(n|n\.|n\-|η|Ν|Π)',
            'o' => '(o|o\.|o\-|0|Ο|ο|Φ|¤|°|ø)',
            'p' => '(p|p\.|p\-|ρ|Ρ|¶|þ)',
            'q' => '(q|q\.|q\-)',
            'r' => '(r|r\.|r\-|®)',
            's' => '(s|s\.|s\-|5|\$|§)',
            't' => '(t|t\.|t\-|Τ|τ|7)',
            'u' => '(u|u\.|u\-|υ|µ)',
            'v' => '(v|v\.|v\-|υ|ν)',
            'w' => '(w|w\.|w\-|ω|ψ|Ψ)',
            'x' => '(x|x\-|Χ|χ)',
            'y' => '(y|y\.|y\-|¥|γ|ÿ|ý|Ÿ|Ý)',
            'z' => '(z|z\.|z\-|Ζ)',
        ];
    }


    private function generateCensorChecks(bool $fullWords = false): void {
        if (empty($this->badwords)) return;

        $this->compileLeetDictionary();
        $separator = '[\s\.\-_]*';
        $patterns = [];

        foreach ($this->badwords as $badword) {
            if (strpos($badword, 'regex:') === 0) {
                $pattern = substr($badword, 6);
                $pattern = trim($pattern, '#/');
                $patterns[] = $pattern;
            } else {
                $chars = preg_split('//u', $badword, -1, PREG_SPLIT_NO_EMPTY);
                $patternParts = [];

                foreach ($chars as $char) {
                    $lowerChar = mb_strtolower($char, 'UTF-8');
                    $patternParts[] = $this->leet_replace[$lowerChar] ?? preg_quote($char, '#');
                }

                $wordPattern = implode($separator, $patternParts);
                $patterns[] = $fullWords ? '\b' . $wordPattern . '\b' : $wordPattern;
            }
        }

        $this->censorChecks = '#(' . implode('|', $patterns) . ')#ui';
    }


    private function toggleWhitelist(string $text, bool $revert = false): string {
        if (empty($this->whiteList)) return $text;

        static $map = [];
        if (empty($map)) {
            foreach ($this->whiteList as $k => $word) {
                $placeholder = sprintf($this->whiteListPlaceHolder, $k);
                $map[$word] = $placeholder;
            }
        }

        return $revert
        ? str_replace(array_values($map), array_keys($map), $text)
        : str_replace(array_keys($map), array_values($map), $text);
    }
    public function censorString(string $string, bool $fullWords = false): array {
        if (empty($string)) return ['orig' => '', 'clean' => '', 'matched' => []];

        $chunks = array_chunk($this->badwords, 30);
        $workingString = $this->toggleWhitelist($string);
        $matchesFound = [];

        foreach ($chunks as $chunk) {
            $currentPattern = $this->generatePatternFromList($chunk, $fullWords);

            if (empty($currentPattern)) continue;

            $result = preg_replace_callback(
                $currentPattern,
                function ($matches) use (&$matchesFound) {
                    $matchesFound[] = $matches[0];
                    return str_repeat($this->replacer, mb_strlen($matches[0], 'UTF-8'));
                },
                $workingString
            );

            if ($result !== null) {
                $workingString = $result;
            }
        }

        $cleanString = $this->toggleWhitelist($workingString, true);

        return [
            'orig' => $string,
            'clean' => $cleanString,
            'matched' => $matchesFound
        ];
    }

    private function generatePatternFromList(array $list, bool $fullWords): string {
        $this->compileLeetDictionary();
        $separator = '[\s\.\-_]*';
        $patterns = [];

        foreach ($list as $badword) {
            if (strpos($badword, 'regex:') === 0) {
                $patterns[] = trim(substr($badword, 6), '#/');
            } else {
                $chars = preg_split('//u', $badword, -1, PREG_SPLIT_NO_EMPTY);
                $patternParts = [];

                foreach ($chars as $char) {
                    $lowerChar = mb_strtolower($char, 'UTF-8');
                    $patternParts[] = $this->leet_replace[$lowerChar] ?? preg_quote($char, '#');
                }

                $wordPattern = implode($separator, $patternParts);
                $patterns[] = $fullWords ? '(?<!\w)' . $wordPattern . '(?!\w)' : $wordPattern;
            }
        }

        return !empty($patterns) ? '#(' . implode('|', $patterns) . ')#ui' : '';
    }
}