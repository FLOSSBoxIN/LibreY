<?php
    function get_base_url($url) {
        $parsed = parse_url($url);

        if (isset($parsed["scheme"]) && isset($parsed["host"]) && !empty($parsed["scheme"]) && !empty($parsed["host"])) {
            return $parsed["scheme"] . "://" . $parsed["host"] . "/";
        } else {
			logStackTrace();
            return "";
        }
    }

    function seconds_to_human_readable($inputSeconds) {
      $secondsInAMinute = 60;
      $secondsInAnHour = 60 * $secondsInAMinute;
      $secondsInADay = 24 * $secondsInAnHour;
  
      $days = floor($inputSeconds / $secondsInADay);
      $hourSeconds = $inputSeconds % $secondsInADay;
      $hours = floor($hourSeconds / $secondsInAnHour);
      $minuteSeconds = $hourSeconds % $secondsInAnHour;
      $minutes = floor($minuteSeconds / $secondsInAMinute);
      $remainingSeconds = $minuteSeconds % $secondsInAMinute;
      $seconds = ceil($remainingSeconds);
      $timeParts = [];
      $sections = [
          'day' => (int)$days,
          'hour' => (int)$hours,
          'minute' => (int)$minutes,
          'second' => (int)$seconds,
      ];
  
      foreach ($sections as $name => $value){
        if ($value > 0){
          $timeParts[] = $value. ' '.$name.($value == 1 ? '' : 's');
        }
      }
  
      return implode(', ', $timeParts);
    }

    function get_root_domain($url) {
        return parse_url($url, PHP_URL_HOST);
    }

    function try_replace_with_frontend($url, $frontend, $original, $opts) {
        $frontends = $opts->frontends;

        if (array_key_exists($frontend, $opts->frontends)) {
            $frontend = $frontends[$frontend]["instance_url"];

            if (empty(trim($frontend)))
                return $url;

            if (strpos($url, "wikipedia.org") !== false) {
                $wiki_split = explode(".", $url);
                if (count($wiki_split) > 1) {
                    $lang = explode("://", $wiki_split[0])[1];
                    $url =  $frontend . explode($original, $url)[1] . (strpos($url, "?") !== false ? "&" : "?")  . "lang=" . $lang;
                }
            } else if (strpos($url, "fandom.com") !== false) {
                $fandom_split = explode(".", $url);
                if (count($fandom_split) > 1) {
                    $wiki_name = explode("://", $fandom_split[0])[1];
                    $url =  $frontend . "/" . $wiki_name . explode($original, $url)[1];
                }
            } else if (strpos($url, "gist.github.com") !== false) {
                $gist_path = explode("gist.github.com", $url)[1];
                $url = $frontend . "/gist" . $gist_path;
            } else if (strpos($url, "stackexchange.com") !== false) {
                $se_domain = explode(".", explode("://", $url)[1])[0];
                $se_path = explode("stackexchange.com", $url)[1];
                $url = $frontend . "/exchange" . "/" . $se_domain . $se_path;
            } else {
                $url =  $frontend . explode($original, $url)[1];
            }


            return $url;
        }

        return $url;
    }

    function check_for_privacy_frontend($url, $opts) {
        if ($opts->disable_frontends)
            return $url;

        foreach($opts->frontends as $frontend => $data) {
            $original = $data["original_url"];

            if (strpos($url, $original)) {
                $url = try_replace_with_frontend($url, $frontend, $original, $opts);
                break;
            } else if (strpos($url, "stackexchange.com")) {
                $url = try_replace_with_frontend($url, "anonymousoverflow", "stackexchange.com", $opts);
                break;
            }
        }

        return $url;
    }

    function get_xpath($response) {
        if (!$response)
            return null;

        $htmlDom = new DOMDocument;
        @$htmlDom->loadHTML($response);
        $xpath = new DOMXPath($htmlDom);

        return $xpath;
    }

    function request($url, $conf) {
        $ch = curl_init($url);
        curl_setopt_array($ch, $conf);
        $response = curl_exec($ch);

        return $response;
    }

    function human_filesize($bytes, $dec = 2) {
        $size   = array('B', 'kB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB');
        $factor = floor((strlen($bytes) - 1) / 3);

        return sprintf("%.{$dec}f ", $bytes / pow(1024, $factor)) . @$size[$factor];
    }

    function remove_special($string) {
        $string = preg_replace("/[\r\n]+/", "\n", $string);
        return trim(preg_replace("/\s+/", ' ', $string));
     }

    function print_elapsed_time($start_time, $results, $opts) {
            $source = "";
            if (array_key_exists("results_source", $results)) {
                $source = " from " . $results["results_source"];
                unset($results["results_source"]);
            }

            $end_time = number_format(microtime(true) - $start_time, 2, '.', '');
            echo "<p id=\"time\">Fetched the results in $end_time seconds$source</p>";
        }

    function print_next_page_button($text, $page, $query, $type) {
        echo "<form class=\"page\" action=\"search.php\" target=\"_top\" method=\"get\" autocomplete=\"off\">";
        echo "<input type=\"hidden\" name=\"p\" value=\"" . $page . "\" />";
        echo "<input type=\"hidden\" name=\"q\" value=\"$query\" />";
        echo "<input type=\"hidden\" name=\"t\" value=\"$type\" />";
        echo "<button type=\"submit\">$text</button>";
        echo "</form>";
    }

    function copy_cookies($curl) {
        if (array_key_exists("HTTP_COOKIE", $_SERVER))
            curl_setopt( $curl, CURLOPT_COOKIE, $_SERVER['HTTP_COOKIE'] );
    }

    
    function get_country_emote($code) {
        $emoji = [];
        foreach(str_split($code) as $c) {
            if(($o = ord($c)) > 64 && $o % 32 < 27) {
                $emoji[] = hex2bin("f09f87" . dechex($o % 32 + 165));
                continue;
            }
            
            $emoji[] = $c;
        }

        return join($emoji);
    }

    function logStackTrace() {
        // Get the stack trace
        $stackTrace = debug_backtrace();

        // Format the stack trace for logging
        $logMessage = "Stack Trace: ";
        foreach ($stackTrace as $index => $trace) {
            // Skip the first entry as it's the current function call
            if ($index === 0) {
                continue;
            }

            // Build the log message for each stack frame
            $logMessage .= "#{$index} ";
            if (isset($trace['file'])) {
                $logMessage .= "File: {$trace['file']} ";
            }
            if (isset($trace['line'])) {
                $logMessage .= "Line: {$trace['line']} ";
            }
            if (isset($trace['function'])) {
                $logMessage .= "Function: {$trace['function']} ";
            }
            $logMessage .= "\n";
        }

        // Log the stack trace to the error log
        error_log($logMessage);
    }

?>
