<?php
require 'loader.php';

echo 'Ready to send emails<br/>';

$receivers = $repo->getReceivers();

foreach ($receivers as $receiver) {
    echo 'Send email to ' . $receiver['name'] . '(' . $receiver['email'] . ')<br/>';

    $loves = $repo->getLoves($receiver['id']);

    if (!$loves) {
        echo 'Nothing to send :(<br/>';
        continue;
    }

    $content = '';
    foreach ($loves as $love) {
        $content .= '<p style="font-size:30px;text-align:left;margin:0;padding:0">&ldquo;</p>' .
            '<p style="font-size:16px;text-align:center;margin:0;padding:0"><i>' . nl2br($love['content']) . '</i></p>' .
            '<p style="font-size:30px;text-align:right;margin:0;padding:0">&rdquo;</p>' .
            '<p style="ont-size:16px;font-weight:bold;text-align:right;"">' . $love['sender'] . '</p>' .
            '<br/><img src="http://ageheureux.a.g.pic.centerblog.net/guirlandes-0048_1.gif" width="100%"/><br/>';
    }

    $mailjetClient = new \Mailjet\Client($_SERVER['MailjetPublicKey'], $_SERVER['MailjetPrivateKey'], true, ['version' => 'v3.1']);
    $body = [
        'Messages' => [
            [
                'From' => [
                    'Email' => $_SERVER['MailFromEmail'],
                    'Name' => $_SERVER['MailFromName']
                ],
                'To' => [
                    [
                        'Email' => $receiver['email'],
                    ],
                ],
                'Subject' => $_SERVER['MailSubject'],
                'Variables' => [
                    'content' => $content
                ],
                'TemplateID' => (int)$_SERVER['MailTemplateId'],
                'TemplateLanguage' => true,
            ]
        ]
    ];
    $response = $mailjetClient->post(\Mailjet\Resources::$Email, ['body' => $body]);
    if (!$response->success()) {
        echo 'error<br/>';
        print_r($response->getData());
        echo '<br/>';
    } else {
        echo 'ok<br/>';
    }
}
