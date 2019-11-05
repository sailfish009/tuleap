<?php
/**
 * Copyright (c) Enalean, 2019-Present. All Rights Reserved.
 *
 * This file is a part of Tuleap.
 *
 * Tuleap is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * Tuleap is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Tuleap. If not, see <http://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace Tuleap\Taskboard\Column\FieldValuesToColumnMapping\Freestyle;

use Mockery as M;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use Tracker;
use Tracker_FormElementFactory;

final class FreestyleMappingFactoryTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    /** @var FreestyleMappingFactory */
    private $freestyle_mapping_factory;
    /** @var M\LegacyMockInterface|M\MockInterface|FreestyleMappingDao */
    private $dao;
    /** @var M\LegacyMockInterface|M\MockInterface|Tracker_FormElementFactory */
    private $form_element_factory;

    protected function setUp(): void
    {
        $this->dao                       = M::mock(FreestyleMappingDao::class);
        $this->form_element_factory      = M::mock(Tracker_FormElementFactory::class);
        $this->freestyle_mapping_factory = new FreestyleMappingFactory($this->dao, $this->form_element_factory);
    }

    public function testGetMappedFieldReturnsNullWhenNoMapping(): void
    {
        $milestone_tracker = M::mock(Tracker::class);
        $tracker           = M::mock(Tracker::class);
        $this->dao->shouldReceive('searchMappedField')
            ->once()
            ->with($milestone_tracker, $tracker)
            ->andReturnNull();

        $result = $this->freestyle_mapping_factory->getMappedField($milestone_tracker, $tracker);
        $this->assertNull($result);
    }

    public function testGetMappedFieldReturnsNullWhenFieldIsNotSelectbox(): void
    {
        $milestone_tracker = M::mock(Tracker::class);
        $tracker           = M::mock(Tracker::class);
        $this->dao->shouldReceive('searchMappedField')
            ->once()
            ->with($milestone_tracker, $tracker)
            ->andReturn(123);
        $field = M::mock(\Tracker_FormElement_Field_OpenList::class);
        $this->form_element_factory->shouldReceive('getUsedListFieldById')
            ->with($tracker, 123)
            ->once()
            ->andReturn($field);

        $result = $this->freestyle_mapping_factory->getMappedField($milestone_tracker, $tracker);
        $this->assertNull($result);
    }

    public function testGetMappedFieldReturnsMappedSelectbox(): void
    {
        $milestone_tracker = M::mock(Tracker::class);
        $tracker           = M::mock(Tracker::class);
        $this->dao->shouldReceive('searchMappedField')
            ->once()
            ->with($milestone_tracker, $tracker)
            ->andReturn(123);
        $field = M::mock(\Tracker_FormElement_Field_Selectbox::class);
        $this->form_element_factory->shouldReceive('getUsedListFieldById')
            ->with($tracker, 123)
            ->once()
            ->andReturn($field);

        $result = $this->freestyle_mapping_factory->getMappedField($milestone_tracker, $tracker);
        $this->assertNotNull($result);
        $this->assertSame($field, $result);
    }

    public function testGetMappedFieldReturnsMappedMultiSelectbox(): void
    {
        $milestone_tracker = M::mock(Tracker::class);
        $tracker           = M::mock(Tracker::class);
        $this->dao->shouldReceive('searchMappedField')
            ->once()
            ->with($milestone_tracker, $tracker)
            ->andReturn(123);
        $field = M::mock(\Tracker_FormElement_Field_MultiSelectbox::class);
        $this->form_element_factory->shouldReceive('getUsedListFieldById')
            ->with($tracker, 123)
            ->once()
            ->andReturn($field);

        $result = $this->freestyle_mapping_factory->getMappedField($milestone_tracker, $tracker);
        $this->assertNotNull($result);
        $this->assertSame($field, $result);
    }

    public function testDoesFreestyleMappingExistDelegatesToDAO(): void
    {
        $milestone_tracker = M::mock(Tracker::class);
        $tracker           = M::mock(Tracker::class);
        $this->dao->shouldReceive('doesFreestyleMappingExist')
            ->once()
            ->with($milestone_tracker, $tracker)
            ->andReturnTrue();

        $this->assertTrue($this->freestyle_mapping_factory->doesFreestyleMappingExist($milestone_tracker, $tracker));
    }

    public function testGetValuesMappedToColumnReturnsEmpty(): void
    {
        $milestone_tracker = M::mock(Tracker::class);
        $tracker           = M::mock(Tracker::class);
        $todo_column       = new \Cardwall_Column(12, 'Todo', 'acid-green');
        $this->dao->shouldReceive('searchMappedFieldValuesForColumn')
            ->with($milestone_tracker, $tracker, $todo_column)
            ->once()
            ->andReturn([]);

        $result = $this->freestyle_mapping_factory->getValuesMappedToColumn($milestone_tracker, $tracker, $todo_column);
        $this->assertSame(0, count($result->getValueIds()));
    }

    public function testGetValuesMappedToColumnReturnsValues(): void
    {
        $milestone_tracker = M::mock(Tracker::class);
        $tracker           = M::mock(Tracker::class);
        $todo_column       = new \Cardwall_Column(12, 'Todo', 'acid-green');
        $this->dao->shouldReceive('searchMappedFieldValuesForColumn')
            ->with($milestone_tracker, $tracker, $todo_column)
            ->once()
            ->andReturn([['value_id' => 123], ['value_id' => 127]]);

        $result        = $this->freestyle_mapping_factory->getValuesMappedToColumn(
            $milestone_tracker,
            $tracker,
            $todo_column
        );
        $mapped_values = $result->getValueIds();
        $this->assertSame(2, count($mapped_values));
        $this->assertSame([123, 127], $mapped_values);
    }
}
