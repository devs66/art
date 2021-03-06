<?php

return [
    'id' => 'ID',
    'title' => 'název',
    'description' => 'popis',
    'description_source' => 'popis - zdroj',
    'work_type' => 'výtvarný druh',
    'object_type' => 'typ díla',
    'work_level' => 'stupeň spracování',
    'topic' => 'námět',
    'subject' => 'objekt',
    'measurement' => 'rozměry',
    'dating' => 'datace',
    'medium' => 'materiál',
    'technique' => 'technika',
    'inscription' => 'značení',
    'place' => 'místo vzniku',
    'state_edition' => 'původnost',
    'gallery' => 'galerie',
    'credit' => 'nabytí',
    'relationship_type' => 'typ integrity',
    'related_work' => 'název integrity',
    'description_user_id' => 'popis - autor',
    'description_source_link' => 'popis - link na zdroj',
    'identifier' => 'inventární číslo',
    'author' => 'autor',
    'item_authorities' => 'autoři - vztahy',
    'tags' => 'tagy',
    'tag' => 'tag',
    'date_earliest' => 'datace od',
    'date_latest' => 'datace do',
    'lat' => 'latitúda',
    'lng' => 'longitúda',
    'related_work_order' => 'pořadí',
    'related_work_total' => 'z počtu',
    'primary_image' => 'hlavní obrázek',
    'images' => 'obrázky',
    'iipimg_url' => 'IIP URL',
    'filter' => [
        'year_from' => 'od roku',
        'year_to' => 'do roku',
        'has_image' => 'jen s obrázkem',
        'has_iip' => 'jen se zoom',
        'has_text' => 'jen s textem',
        'is_free' => 'jen volné',
        'color' => 'farba',
        'sort_by' => 'podle',
        'sorting' => [
            'created_at' => 'data přidání',
            'title' => 'názvu',
            'relevance' => 'relevancie',
            'updated_at' => 'poslední změny',
            'author' => 'autora',
            'newest' => 'datování – od najnovějšího',
            'oldest' => 'datování – od najstaršího',
            'view_count' => 'počtu vidění',
            'random' => 'náhodného pořadí'
        ],
        'title_generator' => [
            'search' => 'výsledky vyhledávání pro: ":value"',
            'author' => 'autor: :value',
            'work_type' => 'výtvarný druh: :value',
            'tag' => 'tagy: :value',
            'gallery' => 'galerie: :value',
            'credit' => 'nabytí: :value',
            'topic' => 'námět: :value',
            'medium' => 'materiál: :value',
            'technique' => 'technika: :value',
            'has_image' => 'jen s obrázkom',
            'has_iip' => 'jen so zoom',
            'is_free' => 'jen voľné',
            'related_work' => 'ze souboru: :value',
            'years' => 'v letech :from — :to',
        ],
    ],
    'importer' => [
        'work_type' => [
            'graphics' => 'grafika',
            'drawing' => 'kresba',
            'image' => 'obraz',
            'sculpture' => 'sochařství',
            'applied_arts' => 'užité umění',
            'photography' => 'fotografie',
        ],
    ],
    'measurement_replacements' => [
        'a' => 'výška hlavní části',
        'a.' => 'výška hlavní části',
        'b' => 'výška vedlejší části',
        'b.' => 'výška vedlejší části',
        'čas' => 'čas',
        'd' => 'délka',
        'd.' => 'délka',
        'h' => 'hloubka/tloušťka',
        'h.' => 'hloubka/tloušťka',
        'hmot' => 'hmotnost',
        'hmot.' => 'hmotnost',
        'hr' => 'hloubka s rámem',
        'jiný' => 'jiný nespecifikovaný',
        'p' => 'průměr/ráže',
        'p.' => 'průměr/ráže',
        'r.' => 'ráže',
        'ryz' => 'ryzost',
        's' => 'šířka',
        's.' => 'šířka',
        'sd.' => 'šířka grafické desky',
        'sp' => 'šířka s paspartou',
        'sp.' => 'šířka s paspartou',
        'sr' => 'šířka s rámem',
        'v' => 'celková výška/délka',
        'v.' => 'celková výška/délka',
        'vd.' => 'výška grafické desky',
        'vp' => 'výška s paspartou',
        'vp.' => 'výška s paspartou',
        'vr' => 'výška s rámem',
        '=' => ' ',
    ],
];
