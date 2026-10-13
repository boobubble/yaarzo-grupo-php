<?php

include 'fns/curl/autoload.php';
use Curl\Curl;

if (Registry::load('settings')->gif_search_engine !== 'disable') {
    if (role(['permissions' => ['private_conversations' => 'attach_gifs', 'groups' => 'attach_gifs'], 'condition' => 'OR'])) {

        $limit = Registry::load('settings')->gifs_per_load;
        $data["offset"] = htmlspecialchars($data["offset"]);
        $content_filter = '';

        if (Registry::load('settings')->gif_search_engine === 'klipy') {

            $search_query = '';
            $app_key = Registry::load('settings')->gif_search_engine_api;
            $page = !empty($data["offset"]) ? (int)$data["offset"] : 1;

            if (Registry::load('settings')->gif_content_filtering === 'high') {
                $content_filter = '&rating=g';
            } else if (Registry::load('settings')->gif_content_filtering === 'medium') {
                $content_filter = '&rating=pg';
            } else if (Registry::load('settings')->gif_content_filtering === 'low') {
                $content_filter = '&rating=pg-13';
            }

            if (!empty($data["search"])) {
                $endpoint = 'search';
                $search_query = '&q=' . urlencode(htmlspecialchars($data["search"]));
            } else {
                $endpoint = 'trending';
            }

            $gif_url = "https://api.klipy.com/api/v1/{$app_key}/gifs/{$endpoint}?page={$page}&per_page={$limit}{$search_query}{$content_filter}";

        } else if (Registry::load('settings')->gif_search_engine === 'giphy') {

            $search = 'trending?';

            if (!empty($data["search"])) {
                $search = 'search?q='.urlencode(htmlspecialchars($data["search"]));
            }

            if (Registry::load('settings')->gif_content_filtering === 'high') {
                $content_filter = '&rating=g';
            } else if (Registry::load('settings')->gif_content_filtering === 'medium') {
                $content_filter = '&rating=pg';
            } else if (Registry::load('settings')->gif_content_filtering === 'low') {
                $content_filter = '&rating=pg-13';
            }

            $gif_url = 'https://api.giphy.com/v1/gifs/'.$search.'&limit='.$limit.$content_filter;

            if (!empty(Registry::load('settings')->gif_search_engine_api)) {
                $gif_url = $gif_url.'&api_key='.Registry::load('settings')->gif_search_engine_api;
            }

            if (!empty($data["offset"])) {
                $gif_url = $gif_url.'&offset='.$data["offset"];
            }


        }

        $curl = new Curl();
        $curl->setOpt(CURLOPT_TIMEOUT, 15);
        $curl->setOpt(CURLOPT_ENCODING, '');
        $curl->get($gif_url);

        if (!$curl->error) {
            $response = $curl->response;
            $gif_list = [];

            if (Registry::load('settings')->gif_search_engine === 'klipy') {
                $results = isset($response->data->data) ? $response->data->data : [];
                $result_count = count($results);

                if ($result_count >= $limit) {
                    $output['offset'] = (int)$page + 1;
                } else {
                    $output['offset'] = 'endofresults';
                }
                $gif_list = $results;

            } else {
                if (isset($response->next) && !empty($response->next)) {
                    $output['offset'] = $response->next;
                } else if (isset($response->cursor) && !empty($response->cursor)) {
                    $output['offset'] = $response->cursor;
                } else if (isset($response->pagination->count)) {
                    $output['offset'] = $response->pagination->count + $response->pagination->offset;
                } else {
                    $output['offset'] = 'endofresults';
                }

                if (isset($response->results)) {
                    $gif_list = $response->results;
                } else if (isset($response->data)) {
                    $gif_list = $response->data;
                }
            }


            $i = 0;

            foreach ($gif_list as $gif) {
                $image_url = null;

                if (isset($gif->file->sm->gif->url)) {
                    $image_url = $gif->file->sm->gif->url;
                } else if (isset($gif->file->md->gif->url)) {
                    $image_url = $gif->file->md->gif->url;
                } else if (isset($gif->media_formats->tinygif->url)) {
                    $image_url = $gif->media_formats->tinygif->url;
                } else if (isset($gif->images->downsized->url)) {
                    $image_url = $gif->images->downsized->url;
                } else if (isset($gif->url)) {
                    $image_url = $gif->url;
                }

                if (!empty($image_url)) {
                    $output['content'][$i] = new stdClass();
                    $output['content'][$i]->image = $image_url;
                    $output['content'][$i]->attributes = ['media_url' => $image_url, 'media_type' => 'gif'];
                    $output['content'][$i]->class = "attach_media";
                }
                $i = $i+1;
            }
        }
    }
}