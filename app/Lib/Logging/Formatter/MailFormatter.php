<?php
/**
 * Created by PhpStorm.
 * User: Exodus 4D
 * Date: 07.10.2017
 * Time: 14:49
 */

namespace Exodus4D\Pathfinder\Lib\Logging\Formatter;

use Exodus4D\Pathfinder\Lib\Config;
use Monolog\Formatter;
use Monolog\LogRecord;

class MailFormatter implements Formatter\FormatterInterface {

    /**
     * @param array|LogRecord $record
     * @return string
     */
    public function format(array|LogRecord $record): string {
        // Convert LogRecord to array for compatibility with both Monolog 2.x and 3.x
        $recordArray = $record instanceof LogRecord ? $record->toArray() : $record;

        $tplDefaultData = [
            'tplPretext' => $recordArray['message'],
            'tplGreeting' => \Markdown::instance()->convert(str_replace('*', '', $recordArray['message'])),
            'message' => false,
            'tplText2' => false,
            'tplClosing' => 'Fly save!',
            'actionPrimary' => false,
            'appName' => Config::getPathfinderData('name'),
            'appUrl' => Config::getEnvironmentData('URL'),
            'appHost' => $_SERVER['HTTP_HOST'],
            'appContact' => Config::getPathfinderData('contact'),
            'appMail' => Config::getPathfinderData('email'),
        ];

        $tplData = array_replace_recursive($tplDefaultData, (array)$recordArray['context']['data']['main']);

        return \Template::instance()->render('templates/mail/basic_inline.html', 'text/html', $tplData);
    }

    /**
     * @param array $records
     * @return string
     */
    public function formatBatch(array $records): string {
        $message = '';
        foreach ($records as $key => $record) {
            $message .= $this->format($record);
        }

        return $message;
    }

}