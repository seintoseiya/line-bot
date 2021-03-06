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
use LINE\LINEBot\TemplateActionBuilder\MessageTemplateActionBuilder;
use LINE\LINEBot\MessageBuilder\MultiMessageBuilder;
use LINE\LINEBot\MessageBuilder\TemplateMessageBuilder;
use LINE\LINEBot\MessageBuilder\TextMessageBuilder;
use LINE\LINEBot\MessageBuilder\TemplateBuilder\ConfirmTemplateBuilder;
use LINE\LINEBot\Event\MessageEvent\ImageMessage;
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
                if ($event instanceof ImageMessage) {
                    $response = $bot->getMessageContent($event->getMessageId());
                    if ($response->isSucceeded()) {
                        $tempfile = tmpfile();
                        fwrite($tempfile, $response->getRawBody());
                    } else {
                        error_log($response->getHTTPStatus() . ' ' . $response->getRawBody());
                    }
                    $replyText = nekojudge($tempfile);
                // }elseif ($event instanceof PostbackEvent) {
                //     $res->write('OK');
                //     return $res;
                }else{
                    $logger->info('Non message event has come');
                    $replyText = "猫の画像を送信してね。";
                }

                // $text_message = new TextMessageBuilder($replyText);
                // $confirm_message = new TemplateMessageBuilder(
                //         'Confirm alt text',
                //         new ConfirmTemplateBuilder('Do it?', [
                //         new MessageTemplateActionBuilder('Yes', 'Yes!'),
                //         new MessageTemplateActionBuilder('No', 'No!'),
                //     ])
                // );

                // $message = new MultiMessageBuilder();
                // $message->add($text_message);
                // $message->add($confirm_message);
                // $resp = $bot->replyMessage($event->getReplyToken(), $message);

                $resp = $bot->replyText($event->getReplyToken(), $replyText);
                // $resp2 = $bot->replyMessage(
                //     $event->getReplyToken(),
                //     new TemplateMessageBuilder(
                //         'Confirm alt text',
                //         new ConfirmTemplateBuilder('Do it?', [
                //             new MessageTemplateActionBuilder('Yes', 'Yes!'),
                //             new MessageTemplateActionBuilder('No', 'No!'),
                //         ])
                //     )
                // );
                $logger->info($resp->getHTTPStatus() . ': ' . $resp->getRawBody());
            }

            $res->write('OK');
            return $res;
        });

        /**
         * Returns text of the message.
         * parameter: binary
         * @return string
         */
        function nekojudge($send_image) {
            $image_uri = stream_get_meta_data($send_image)[uri];
            $curl = curl_init();
            $api_url = 'http://whatcat.ap.mextractr.net/api_query';
            $cfile = curl_file_create($image_uri,'image/jpeg','test_name');
            $params = array('image' => $cfile);

            curl_setopt($curl, CURLOPT_URL, $api_url);
            curl_setopt($curl, CURLOPT_USERPWD, "hoge:hoge");
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: multipart/form-data'));
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $params );
            error_log("params:".print_r($params,true));

            $data = curl_exec($curl);
            $res = json_decode($data);
            error_log("res[0]:".print_r($res[0],true));

            $ans = "おそらく".$res[0][0]."だにゃ。";
            return $ans;
        }
    }
}
