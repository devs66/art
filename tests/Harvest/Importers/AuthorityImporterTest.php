<?php

namespace Tests\Harvest\Importers;

use App\Authority;
use App\AuthorityRelationship;
use App\Harvest\Importers\AuthorityImporter;
use App\Harvest\Mappers\AuthorityEventMapper;
use App\Harvest\Mappers\AuthorityMapper;
use App\Harvest\Mappers\AuthorityNameMapper;
use App\Harvest\Mappers\AuthorityNationalityMapper;
use App\Harvest\Mappers\AuthorityRelationshipMapper;
use App\Harvest\Mappers\NationalityMapper;
use App\Harvest\Mappers\RelatedAuthorityMapper;
use App\Harvest\Progress;
use App\Nationality;
use Elasticsearch\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorityImporterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance(Client::class, $this->createMock(Client::class));
    }

    public function testUpdateRelated() {
        factory(Authority::class)->create(['id' => 1000162]);
        factory(Authority::class)->create(['id' => 1000168]);
        factory(Authority::class)->create(['id' => 11680]);

        $row = $this->getData();
        $importer = $this->initImporter($row);

        $authority = $importer->import($row, new Progress());
    }

    public function testExistingButNotRelatedYet() {
        factory(Nationality::class)->create([
            'id' => 12277,
            'code' => 'Slovensko',
        ]);

        $row = $this->getData();
        $importer = $this->initImporter($row);

        $authority = $importer->import($row, new Progress());
        $this->assertEquals(1, $authority->nationalities->count());
    }

    public function testRelatedButNotExisting() {
        factory(AuthorityRelationship::class)->create([
            'authority_id' => 954,
            'related_authority_id' => 1000162,
            'type' => '',
        ]);

        $row = $this->getData();
        $importer = $this->initImporter($row);

        $authority = $importer->import($row, new Progress());
        $this->assertEquals(0, $authority->relationships->count());
    }

    protected function getData() {
        return [
            'identifier' => ['954'],
            'datestamp' => ['2015-02-16T22:55:34Z'],
            'birth_place' => ['Pova??sk?? Bystrica'],
            'death_place' => ['Bratislava'],
            'type_organization' => ['Zbierkotvorn?? gal??ria'],
            'biography' => ['AUTOR: Bl??hov?? Irena (ZN??MY) http://example.org/'],
            'id' => ['urn:svk:psi:per:sng:0000000954'],
            'type' => ['Person'],
            'name' => ['Bl??hov??, Irena'],
            'sex' => ['Female'],
            'birth_date' => ['02.03.1904'],
            'death_date' => ['30.11.1991'],
            'roles' => ['fotograf/photographer'],
            'names' => [
                [
                    'name' => ['Bl??hov??, Irena'],
                ],
            ],
            'nationalities' => [
                [
                    'id' => ['urn:svk:psi:per:sng:0000012277'],
                    'code' => ['Slovensko'],
                ],
            ],
            'events' => [
                [
                    'id' => ['1000166'],
                    'event' => ['??t??dium/study'],
                    'place' => ['Berl??n'],
                    'start_date' => ['1931'],
                    'end_date' => ['1932'],
                ],
            ],
            'relationships' => [
                [
                    'type' => ['??tudent (osoba - in??tit??cia)/student at (person to institution)'],
                    'related_authority_id' => ['urn:svk:psi:per:sng:0001000162'],
                ],
                [
                    'type' => ['??len/member'],
                    'related_authority_id' => ['urn:svk:psi:per:sng:0001000168'],
                ],
                [
                    'type' => ['partner/partner'],
                    'related_authority_id' => ['urn:svk:psi:per:sng:0000011680'],
                ],
            ],
            'links' => [
                [
                    'url' => ['http://example.org/'],
                ]
            ],
        ];
    }

    protected function initImporter(array $row) {
        $importer = new AuthorityImporter(
            $authorityMapperMock = $this->createMock(AuthorityMapper::class),
            $authorityEventMapperMock = $this->createMock(AuthorityEventMapper::class),
            $authorityNameMapperMock = $this->createMock(AuthorityNameMapper::class),
            $authorityNationalityMapperMock = $this->createMock(AuthorityNationalityMapper::class),
            $authorityRelationshipMapperMock = $this->createMock(AuthorityRelationshipMapper::class),
            $nationalityMapperMock = $this->createMock(NationalityMapper::class),
            $relatedAuthorityMapperMock = $this->createMock(RelatedAuthorityMapper::class)
        );

        $authorityMapperMock
            ->expects($this->once())
            ->method('map')
            ->with($row)
            ->willReturn([
                'id' => 954,
                'type' => 'person',
                'name' => 'Bl??hov??, Irena',
                'sex' => 'female',
                'birth_date' => '02.03.1904',
                'death_date' => '30.11.1991',
                'birth_year' => 1904,
                'death_year' => 1991,
                'roles:sk' => ['fotograf'],
                'type_organization:sk' => 'Zbierkotvorn?? gal??ria',
                'biography:sk' => '',
                'birth_place:sk' => 'Pova??sk?? Bystrica',
                'death_place:sk' => 'Bratislava',
                'roles:en' => ['photographer'],
                'type_organization:en' => 'Zbierkotvorn?? gal??ria',
                'biography:en' => '',
                'birth_place:en' => null,
                'death_place:en' => null,
                'roles:cs' => [null],
                'type_organization:cs' => 'Zbierkotvorn?? gal??ria',
                'biography:cs' => '',
                'birth_place:cs' => null,
                'death_place:cs' => null,
            ]);
        $authorityEventMapperMock
            ->expects($this->exactly(1))
            ->method('map')
            ->withConsecutive([$row['events'][0]])
            ->willReturnOnConsecutiveCalls([
                'id' => '1000166',
                'event' => '??t??dium',
                'place' => 'Berl??n',
                'start_date' => '1931',
                'end_date' => '1932',
                'prefered' => false,
            ]);
        $authorityNameMapperMock
            ->expects($this->exactly(1))
            ->method('map')
            ->withConsecutive([$row['names'][0]])
            ->willReturnOnConsecutiveCalls([
                'name' => 'Bl??hov??, Irena',
                'prefered' => false,
            ]);
        $authorityNationalityMapperMock
            ->expects($this->exactly(1))
            ->method('map')
            ->withConsecutive([$row['nationalities'][0]])
            ->willReturnOnConsecutiveCalls(
                ['prefered' => false]
            );
        $authorityRelationshipMapperMock
            ->method('map')
            ->withConsecutive(
                [$row['relationships'][0]],
                [$row['relationships'][1]],
                [$row['relationships'][2]]
            )
            ->willReturnOnConsecutiveCalls(
                ['type' => '??tudent (osoba - in??tit??cia)'],
                ['type' => '??len'],
                ['type' => 'partner']
            );

        $nationalityMapperMock
            ->expects($this->exactly(1))
            ->method('map')
            ->withConsecutive([$row['nationalities'][0]])
            ->willReturnOnConsecutiveCalls(
                [
                    'id' => 12277,
                    'code' => 'Slovensko',
                ]
            );
        $relatedAuthorityMapperMock
            ->expects($this->exactly(3))
            ->method('map')
            ->withConsecutive(
                [$row['relationships'][0]],
                [$row['relationships'][1]],
                [$row['relationships'][2]]
            )
            ->willReturnOnConsecutiveCalls(
                ['id' => 1000162],
                ['id' => 1000168],
                ['id' => 11680]
            );

        return $importer;
    }
}
