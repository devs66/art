<?php

namespace App\Harvest\Mappers;

use App\Harvest\Mappers\AbstractMapper;
use App\Item;
use Illuminate\Contracts\Translation\Translator;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;

class GmuhkItemMapper extends AbstractMapper
{
    protected $modelClass = Item::class;

    protected $translator;

    public function __construct(Translator $translator)
    {
        parent::__construct();
        $this->translator = $translator;
    }

    public function mapId(array $row)
    {
        $id = $row['id'][0];
        preg_match('/^.*:(?<gallery>.*)~(?<series>[A-Z]+)(?<number>\d+)(\/(?<part>\d+))?$/i', $id, $matches);
        return sprintf(
            'CZE:%s.%s_%d%s',
            $matches['gallery'],
            $matches['series'],
            $matches['number'],
            isset($matches['part']) ? '_' . (int)$matches['part'] : ''
        );
    }

    public function mapIdentifier(array $row)
    {
        return $row['identifier'][0] ?? '';
    }

    public function mapAuthor(array $row)
    {
        return $row['author'][0];
    }

    public function mapTitle(array $row)
    {
        return $row['title'][0];
    }

    public function mapDating(array $row)
    {
        return $row['dating'][0];
    }

    public function mapTechnique(array $row, $locale)
    {
        $key = sprintf("gmuhk.%s", $row['technique'][0]);
        return $this->translator->get($key, [], $locale);
    }

    public function mapMedium(array $row, $locale)
    {
        $key = sprintf("gmuhk.%s", $row['medium'][0]);
        return $this->translator->get($key, [], $locale);
    }
    
    public function mapMeasurement(array $row, $locale)
    {
        $replacements = $this->translator->get('item.measurement_replacements', [], $locale);
        return strtr($row['measurement'][0], $replacements);
    }
    
    public function mapGallery(array $row)
    {
        return $row['gallery'][0];
    }

    public function mapDescription(array $row)
    {
        return $row['description'][0];
    }

    public function mapDateEarliest(array $row)
    {
        return Date::create($row['date_earliest'][0])->format('Y');
    }

    public function mapDateLatest(array $row)
    {
         return Date::create($row['date_latest'][0])->format('Y');
    }

    public function mapWorkType(array $row, $locale)
    {
        $abbr = Str::after($row['work_type'][0], ':');
        return $this->translator->get(sprintf('gmuhk.work_type.%s', $abbr), [], $locale);
    }
}