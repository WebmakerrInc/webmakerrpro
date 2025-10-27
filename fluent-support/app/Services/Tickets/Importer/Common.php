<?php

namespace FluentSupport\App\Services\Tickets\Importer;

use FluentSupport\App\Models\Person;

class Common
{
    public static function updateOrCreatePerson($personData)
    {
        $emailArray = [
            'email' => $personData['email'],
            'person_type' => $personData['person_type']
        ];

        $person = Person::updateOrCreate($emailArray, $personData);
        return $person->toArray();
    }

    public static function formatPersonData($personData, $type)
    {
        if(!$personData) {
            return [];
        }

        $name = explode(' ', $personData->name);

        return [
            'first_name' => $name[0] ?? '',
            'last_name' => $name[1] ?? '',
            'email' => $personData->email ?? $personData->address,
            'person_type' => $type
        ];
    }

    // Download a file from a remote URL and create a new directory for this if not exists
    // Then save the file to the new directory and move this directory to a new given directory
    public static function downloadFile($remoteUrl, $baseDir, $fileName)
    {
        $filePath = $baseDir . $fileName;

        if (!file_exists($baseDir)) {
            mkdir($baseDir, 0777, true);
        }

        if (!file_exists($filePath)) {
            $file = file_get_contents($remoteUrl);
            file_put_contents($filePath, $file);
        }

        return $filePath;
    }
}
