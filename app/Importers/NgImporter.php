<?php


namespace App\Importers;


use App\Collection;
use App\Repositories\IFileRepository;

class NgImporter extends AbstractImporter {

    protected $options = [
        'delimiter' => ';',
        'enclosure' => '"',
        'escape' => '\\',
        'newline' => "\r\n",
        'input_encoding' => 'CP1250',
    ];

    protected $mapping = [
        'Název díla' => 'title',
        'z cyklu' => 'related_work',
        'Technika' => 'technique',
        'Materiál' => 'medium',
        'Ivent. číslo - zobrazované' => 'identifier',
        'Popis' => 'description',
        'OSA 1' => 'date_earliest',
        'OSA 2' => 'date_latest',
    ];

    protected $defaults = [
        'work_type' => '',
        'topic' => '',
        'relationship_type' => '',
        'place' => '',
        'gallery' => '',
        'description' => '',
        'title' => '',
        'technique' => '',
        'medium' => '',
        'has_rights' => 0,
    ];

    protected static $cz_gallery_collection_spec = [
        'SGK' => 'Sbírka grafiky a kresby',
        'SMSU' => 'Sbírka moderního a současného umění',
        'SUDS' => 'Sbírka umění 19. století',
        'SSU' => 'Sbírka starého umění',
        'SAA' => 'Sbírka umění Asie a Afriky',
    ];

    protected static $name = 'ng';

    public function __construct(IFileRepository $repository) {
        parent::__construct($repository);

        $this->sanitizers[] = function($value) {
            return empty_to_null($value);
        };
    }


    protected function getItemId(array $record) {
        $key = 'Ivent. číslo - zobrazované';
        $id = $record[$key];
        $id = strtr($id, ' ', '_');

        return sprintf('CZE:NG.%s', $id);
    }

    protected function getItemImageFilename(array $record) {
        $key = 'Ivent. číslo - zobrazované';
        $filename = $record[$key];
        $filename = strtr($filename, ' ', '_');

        return $filename;
    }

    protected function getItemIipImageUrl($csv_filename, $image_filename) {
        return sprintf(
            '/NG/jp2/%s.jp2',
            $image_filename
        );
    }

    protected function createItem(array $record) {
        $item = parent::createItem($record);

        $collection = Collection::where('name', $record['Kolekce (budova)'])->first();
        if ($collection) {
            $item->collections()->sync([$collection->id]);
        }

        return $item;
    }

    protected function hydrateAuthor(array $record) {
        $keys = [
            'Autor (jméno příjmení, příp. Anonym)',
            'Autor 2',
            'Autor 3',
        ];

        $keys = array_filter($keys, function ($key) use ($record) {
            return $record[$key] !== null;
        });

        $authors = array_filter(
            array_map(function($key) use ($record) {
                return self::bracketAuthor($record[$key]);
            }, $keys)
        );

        return implode('; ', $authors);
    }

    protected function hydrateInscription(array $record) {
        $key1 = 'značeno kde (umístění v díle)';
        $key2 = 'Značeno (jak např. letopočet, signatura, monogram)';

        $inscription = [];
        if ($record[$key1] !== null) {
            $inscription[] = $record[$key1];
        }

        if ($record[$key2] !== null) {
            $inscription[] = $record[$key2];
        }

        return implode(': ', $inscription);
    }

    protected function hydrateMeasurement(array $record) {
        $measurement = [];

        $width = 'šířka';
        $height = 'výška';
        $depth = 'hloubka';
        $units = 'jednotky';

        $units_suffix = $record[$units] !== null ? sprintf(' %s', $record[$units]) : '';
        if ($record[$height] !== null) {
            $measurement[] = sprintf('výška %s%s', $record[$height], $units_suffix);
        }
        if ($record[$width] !== null) {
            $measurement[] = sprintf('šířka %s%s', $record[$width], $units_suffix);
        }
        if ($record[$depth] !== null) {
            $measurement[] = sprintf('hloubka %s%s', $record[$depth], $units_suffix);
        }

        return implode('; ', $measurement);
    }

    protected function hydrateGalleryCollection(array $record) {
        return isset(self::$cz_gallery_collection_spec[$record['Sbírka']]) ? self::$cz_gallery_collection_spec[$record['Sbírka']] : null;
    }

    protected function hydrateDating(array $record) {
        return $record['Datace'] !== null ? $record['Datace'] : $record['Datování (určené)'];
    }

    protected function hydrateHasRights(array $record) {
        return ($record['Práva'] == 'Ano') ? 1 : 0;
    }

    /**
     * @param string $author
     * @return string
     */
    protected static function bracketAuthor($author) {
        return preg_replace('/,\s*(.*)/', ' ($1)', $author);
    }
}
