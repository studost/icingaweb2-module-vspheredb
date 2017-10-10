<?php

namespace Icinga\Module\Vspheredb\Web\Table;

use Icinga\Module\Vspheredb\DbObject\Datastore;
use Icinga\Module\Vspheredb\Web\Widget\DatastoreUsage;
use Icinga\Util\Format;
use dipl\Html\Link;
use dipl\Web\Table\ZfQueryBasedTable;

class VmsOnDatastoreTable extends ZfQueryBasedTable
{
    protected $searchColumns = [
        'object_name',
    ];

    protected $parentIds;

    /** @var Datastore */
    protected $datastore;

    /** @var int */
    protected $id;

    /** @var int */
    protected $capacity;

    /** @var int */
    protected $uncommitted;

    public static function create(Datastore $datastore)
    {
        $tbl = new static($datastore->getConnection());
        return $tbl->setDatastore($datastore);
    }

    protected function setDatastore(Datastore $datastore)
    {
        $this->datastore   = $datastore;
        $this->id          = $datastore->get('id');
        $this->capacity    = $datastore->get('capacity');
        $this->uncommitted = $datastore->get('uncommitted');
        return $this;
    }

    public function getColumnsToBeRendered()
    {
        return [
            $this->translate('Virtual Machine'),
            $this->translate('Size'),
            $this->translate('Usage'),
            $this->translate('On Datastore'),
        ];
    }

    public function renderRow($row)
    {
        $size = $row->committed + $row->uncommitted;
        $caption = Link::create(
            $row->object_name,
            'vspheredb/vm',
            ['id' => $row->id],
            ['title' => sprintf(
                $this->translate('Virtual Machine: %s'),
                $row->object_name
            )]
        );

        $usage = new DatastoreUsage($this->datastore);
        $usage->setCapacity($size);
        $usage->attributes()->add('class', 'compact');
        $usage->addDiskFromDbRow($row);
        $dsUsage = new DatastoreUsage($this->datastore);
        $dsUsage->attributes()->add('class', 'compact');
        $dsUsage->addDiskFromDbRow($row);

        $tr = $this::tr([
            // TODO: move to CSS
            $this::td($caption, ['style' => 'overflow: hidden; display: inline-block; height: 2em; min-width: 8em;']),
            $this::td(Format::bytes($size, Format::STANDARD_IEC), ['style' => 'white-space: pre;']),
            $this::td($usage, ['style' => 'width: 25%;']),
            $this::td($dsUsage, ['style' => 'width: 25%;'])
        ]);

        return $tr;
    }

    public function prepareQuery()
    {
        $query = $this->db()->select()->from(
            ['o' => 'object'],
            [
                'id'          => 'o.id',
                'object_name' => 'o.object_name',
                'committed'   => 'vdu.committed',
                'uncommitted' => 'vdu.uncommitted',
            ]
        )->join(
            ['vdu' => 'vm_datastore_usage'],
            'vdu.vm_id = o.id',
            []
        )->where('vdu.datastore_id = ?', $this->id)->order('object_name ASC');

        return $query;
    }
}
