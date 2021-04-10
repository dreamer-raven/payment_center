<?php

namespace App\Logging;

use Monolog\Formatter\JsonFormatter as BaseJsonFormatter;

class JsonFormatter extends BaseJsonFormatter
{
    public function format(array $record): string
    {
        $newRecord = [
            'service' => env("APP_NAME"),
            'env' => $record["channel"],
            'level' => $record['level_name'],
            'time' => $record['datetime']->format('Y-m-d H:i:s'),
            'msg' => $record['message'],
        ];
        if (!empty($record['context'])) {
            $newRecord = array_merge($newRecord, $record['context']);
        }
        $json = $this->toJson($this->normalize($newRecord), true) . ($this->appendNewline ? "\n" : '');
        return $json;
    }
}
