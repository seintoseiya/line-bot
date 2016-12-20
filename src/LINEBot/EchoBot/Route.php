<?php

/**
 * Copyright 2016 LINE Corporation
 *
 * LINE Corporation licenses this file to you under the Apache License,
 * version 2.0 (the "License"); you may not use this file except in compliance
 * with the License. You may obtain a copy of the License at:
 *
 *   https://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS, WITHOUT
 * WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied. See the
 * License for the specific language governing permissions and limitations
 * under the License.
 */

namespace LINE\LINEBot\EchoBot;

use LINE\LINEBot;
use LINE\LINEBot\Constant\HTTPHeader;
use LINE\LINEBot\Event\MessageEvent;
use LINE\LINEBot\Event\MessageEvent\TextMessage;
use LINE\LINEBot\Exception\InvalidEventRequestException;
use LINE\LINEBot\Exception\InvalidSignatureException;
use LINE\LINEBot\Exception\UnknownEventTypeException;
use LINE\LINEBot\Exception\UnknownMessageTypeException;

class Route
{
    public function register(\Slim\App $app)
    {
        $app->post('/callback', function (\Slim\Http\Request $req, \Slim\Http\Response $res) {
            /** @var \LINE\LINEBot $bot */
            $bot = $this->bot;
            /** @var \Monolog\Logger $logger */
            $logger = $this->logger;

            $signature = $req->getHeader(HTTPHeader::LINE_SIGNATURE);
            if (empty($signature)) {
                return $res->withStatus(400, 'Bad Request');
            }

            // Check request with signature and parse request
            try {
                $events = $bot->parseEventRequest($req->getBody(), $signature[0]);
            } catch (InvalidSignatureException $e) {
                return $res->withStatus(400, 'Invalid signature');
            } catch (UnknownEventTypeException $e) {
                return $res->withStatus(400, 'Unknown event type has come');
            } catch (UnknownMessageTypeException $e) {
                return $res->withStatus(400, 'Unknown message type has come');
            } catch (InvalidEventRequestException $e) {
                return $res->withStatus(400, "Invalid event request");
            }
            foreach ($events as $event) {
                // if (!($event instanceof MessageEvent)) {
                //     $logger->info('Non message event has come');
                //     continue;
                // }

                // if (!($event instanceof TextMessage)) {
                //     $logger->info('Non text message has come');
                //     continue;
                // }
                $response = $bot->getMessageContent($event->getMessageId());

                if ($response->isSucceeded()) {
                    $tempfile = tmpfile();
                    fwrite($tempfile, $response->getRawBody());
                    $replyText = nekojudge($tempfile);
                } else {
                    error_log($response->getHTTPStatus() . ' ' . $response->getRawBody());
                }

                // $replyText = $event->getText();
                // $logger->info('Reply text: ' . $replyText);
                $resp = $bot->replyText($event->getReplyToken(), $replyText);
                // $logger->info($resp->getHTTPStatus() . ': ' . $resp->getRawBody());
            }

            $res->write('OK');
            return $res;
        });
        /**
         * Returns text of the message.
         * parameter: string
         * @return string
         */
        function chat($send_message) {
            // docomo chatAPI
            $context_file = dirname(__FILE__).'/.docomoapi.context';
            $api_key = '32556d78757870766d767466766d2f6c47507552744b42745342386a4242524f356f2f71554a6a35655637';
            $api_url = sprintf('https://api.apigw.smt.docomo.ne.jp/dialogue/v1/dialogue?APIKEY=%s', $api_key);
            $req_body = array('utt' => $send_message);
            if ( file_exists($context_file) ) {
                $req_body['context'] = file_get_contents($context_file);
            }

            $headers = array(
                'Content-Type: application/json; charset=UTF-8',
            );
            $options = array(
                'http'=>array(
                    'method'  => 'POST',
                    'header'  => implode("\r\n", $headers),
                    'content' => json_encode($req_body),
                    )
                );
            $stream = stream_context_create($options);
            $res = json_decode(file_get_contents($api_url, false, $stream));
            if (isset($res->context)) {
                file_put_contents($context_file, $res->context);
            }

            return $res->utt;
        }
        /**
         * Returns text of the message.
         * parameter: string
         * @return string
         */
        function nekogo($chat_message) {
            // 「。」を「にゃ。」に置換
        }
        /**
         * Returns text of the message.
         * parameter: binary
         * @return string
         */
        function nekojudge($send_image) {
            error_log(print_r($send_image,true));
            // ネコ
            
            // $user = 'seintoseiya';
            // $pass = 'pegasasu';

            $curl = curl_init();
            $api_url = 'http://whatcat.ap.mextractr.net/api_query';
            
            $cfile = new CURLFile($send_image,'image/jpeg','test_name');
            $params = array('image' => $cfile);

            curl_setopt($curl, CURLOPT_URL, $api_url);
            curl_setopt($curl, CURLOPT_USERPWD, "hoge:hoge");
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: multipart/form-data'));
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $params );
            error_log(print_r($params,true));
            $data = curl_exec($curl);
            $res = json_decode($data);

            error_log("1:".print_r($res,true));
            error_log("2:".print_r($res[0],true));
            error_log("3:".print_r($res[0][0],true));
            return $res[0][0];
        }
    }
}
