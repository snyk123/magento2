<?php declare(strict_types=1);
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\Indexer\Test\Unit\Model;

use Magento\Framework\Indexer\ActionFactory;
use Magento\Framework\Indexer\ActionInterface;
use Magento\Framework\Indexer\ConfigInterface;
use Magento\Framework\Indexer\StateInterface;
use Magento\Framework\Indexer\StructureFactory;
use Magento\Framework\Mview\ViewInterface;
use Magento\Indexer\Model\Indexer;
use Magento\Indexer\Model\Indexer\CollectionFactory;
use Magento\Indexer\Model\Indexer\State;
use Magento\Indexer\Model\Indexer\StateFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class IndexerTest extends TestCase
{
    /**
     * @var Indexer|MockObject
     */
    protected $model;

    /**
     * @var ConfigInterface|MockObject
     */
    protected $configMock;

    /**
     * @var ActionFactory|MockObject
     */
    protected $actionFactoryMock;

    /**
     * @var ViewInterface|MockObject
     */
    protected $viewMock;

    /**
     * @var StateFactory|MockObject
     */
    protected $stateFactoryMock;

    /**
     * @var CollectionFactory|MockObject
     */
    protected $indexFactoryMock;

    protected function setUp(): void
    {
        $this->configMock = $this->getMockForAbstractClass(
            ConfigInterface::class,
            [],
            '',
            false,
            false,
            true,
            ['getIndexer']
        );
        $this->actionFactoryMock = $this->createPartialMock(
            ActionFactory::class,
            ['create']
        );
        $this->viewMock = $this->getMockForAbstractClass(
            ViewInterface::class,
            [],
            '',
            false,
            false,
            true,
            ['load', 'isEnabled', 'getUpdated', 'getStatus', '__wakeup', 'getId', 'suspend', 'resume']
        );
        $this->stateFactoryMock = $this->createPartialMock(
            StateFactory::class,
            ['create']
        );
        $this->indexFactoryMock = $this->createPartialMock(
            CollectionFactory::class,
            ['create']
        );
        $structureFactory = $this->getMockBuilder(StructureFactory::class)
            ->disableOriginalConstructor()
            ->setMethods(['create'])
            ->getMock();

        /** @var StructureFactory $structureFactory */
        $this->model = new Indexer(
            $this->configMock,
            $this->actionFactoryMock,
            $structureFactory,
            $this->viewMock,
            $this->stateFactoryMock,
            $this->indexFactoryMock
        );
    }

    public function testLoadWithException()
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage('indexer_id indexer does not exist.');
        $indexId = 'indexer_id';
        $this->configMock->expects(
            $this->once()
        )->method(
            'getIndexer'
        )->with(
            $indexId
        )->will(
            $this->returnValue($this->getIndexerData())
        );
        $this->model->load($indexId);
    }

    public function testGetView()
    {
        $indexId = 'indexer_internal_name';
        $this->viewMock->expects($this->once())->method('load')->with('view_test')->will($this->returnSelf());
        $this->loadIndexer($indexId);

        $this->assertEquals($this->viewMock, $this->model->getView());
    }

    public function testGetState()
    {
        $indexId = 'indexer_internal_name';
        $stateMock = $this->createPartialMock(
            State::class,
            ['loadByIndexer', 'getId', '__wakeup']
        );
        $stateMock->expects($this->once())->method('loadByIndexer')->with($indexId)->will($this->returnSelf());
        $this->stateFactoryMock->expects($this->once())->method('create')->will($this->returnValue($stateMock));

        $this->loadIndexer($indexId);

        $this->assertInstanceOf(State::class, $this->model->getState());
    }

    /**
     * @param bool $getViewIsEnabled
     * @param string $getViewGetUpdated
     * @param string $getStateGetUpdated
     * @dataProvider getLatestUpdatedDataProvider
     */
    public function testGetLatestUpdated($getViewIsEnabled, $getViewGetUpdated, $getStateGetUpdated)
    {
        $indexId = 'indexer_internal_name';
        $this->loadIndexer($indexId);

        $this->viewMock->expects($this->any())->method('getId')->will($this->returnValue(1));
        $this->viewMock->expects($this->once())->method('isEnabled')->will($this->returnValue($getViewIsEnabled));
        $this->viewMock->expects($this->any())->method('getUpdated')->will($this->returnValue($getViewGetUpdated));

        $stateMock = $this->createPartialMock(
            State::class,
            ['load', 'getId', 'setIndexerId', '__wakeup', 'getUpdated']
        );

        $stateMock->expects($this->any())->method('getUpdated')->will($this->returnValue($getStateGetUpdated));
        $this->stateFactoryMock->expects($this->once())->method('create')->will($this->returnValue($stateMock));

        if ($getViewIsEnabled && $getViewGetUpdated) {
            $this->assertEquals($getViewGetUpdated, $this->model->getLatestUpdated());
        } else {
            $getLatestUpdated = $this->model->getLatestUpdated();
            $this->assertEquals($getStateGetUpdated, $getLatestUpdated);

            if ($getStateGetUpdated === null) {
                $this->assertNotNull($getLatestUpdated);
            }
        }
    }

    /**
     * @return array
     */
    public function getLatestUpdatedDataProvider()
    {
        return [
            [false, '06-Jan-1944', '06-Jan-1944'],
            [false, '', '06-Jan-1944'],
            [false, '06-Jan-1944', ''],
            [false, '', ''],
            [true, '06-Jan-1944', '06-Jan-1944'],
            [true, '', '06-Jan-1944'],
            [true, '06-Jan-1944', ''],
            [true, '', ''],
            [true, '06-Jan-1944', '05-Jan-1944'],
            [false, null, null],
        ];
    }

    public function testReindexAll()
    {
        $indexId = 'indexer_internal_name';
        $this->loadIndexer($indexId);

        $stateMock = $this->createPartialMock(
            State::class,
            ['load', 'getId', 'setIndexerId', '__wakeup', 'getStatus', 'setStatus', 'save']
        );
        $stateMock->expects($this->once())->method('load')->with($indexId, 'indexer_id')->will($this->returnSelf());
        $stateMock->expects($this->never())->method('setIndexerId');
        $stateMock->expects($this->once())->method('getId')->will($this->returnValue(1));
        $stateMock->expects($this->exactly(2))->method('setStatus')->will($this->returnSelf());
        $stateMock->expects($this->once())->method('getStatus')->will($this->returnValue('idle'));
        $stateMock->expects($this->exactly(2))->method('save')->will($this->returnSelf());
        $this->stateFactoryMock->expects($this->once())->method('create')->will($this->returnValue($stateMock));

        $this->viewMock->expects($this->once())->method('isEnabled')->will($this->returnValue(true));
        $this->viewMock->expects($this->once())->method('suspend');
        $this->viewMock->expects($this->once())->method('resume');

        $actionMock = $this->createPartialMock(
            ActionInterface::class,
            ['executeFull', 'executeList', 'executeRow']
        );
        $this->actionFactoryMock->expects(
            $this->once()
        )->method(
            'create'
        )->with(
            'Some\Class\Name'
        )->will(
            $this->returnValue($actionMock)
        );

        $this->model->reindexAll();
    }

    public function testReindexAllWithException()
    {
        $this->expectException('Exception');
        $this->expectExceptionMessage('Test exception');
        $indexId = 'indexer_internal_name';
        $this->loadIndexer($indexId);

        $stateMock = $this->createPartialMock(
            State::class,
            ['load', 'getId', 'setIndexerId', '__wakeup', 'getStatus', 'setStatus', 'save']
        );
        $stateMock->expects($this->once())->method('load')->with($indexId, 'indexer_id')->will($this->returnSelf());
        $stateMock->expects($this->never())->method('setIndexerId');
        $stateMock->expects($this->once())->method('getId')->will($this->returnValue(1));
        $stateMock->expects($this->exactly(2))->method('setStatus')->will($this->returnSelf());
        $stateMock->expects($this->once())->method('getStatus')->will($this->returnValue('idle'));
        $stateMock->expects($this->exactly(2))->method('save')->will($this->returnSelf());
        $this->stateFactoryMock->expects($this->once())->method('create')->will($this->returnValue($stateMock));

        $this->viewMock->expects($this->once())->method('isEnabled')->will($this->returnValue(false));
        $this->viewMock->expects($this->never())->method('suspend');
        $this->viewMock->expects($this->once())->method('resume');

        $actionMock = $this->createPartialMock(
            ActionInterface::class,
            ['executeFull', 'executeList', 'executeRow']
        );
        $actionMock->expects($this->once())->method('executeFull')->will(
            $this->returnCallback(
                function () {
                    throw new \Exception('Test exception');
                }
            )
        );
        $this->actionFactoryMock->expects(
            $this->once()
        )->method(
            'create'
        )->with(
            'Some\Class\Name'
        )->will(
            $this->returnValue($actionMock)
        );

        $this->model->reindexAll();
    }

    public function testReindexAllWithError()
    {
        $this->expectException('Error');
        $this->expectExceptionMessage('Test Engine Error');
        $indexId = 'indexer_internal_name';
        $this->loadIndexer($indexId);

        $stateMock = $this->createPartialMock(
            State::class,
            ['load', 'getId', 'setIndexerId', '__wakeup', 'getStatus', 'setStatus', 'save']
        );
        $stateMock->expects($this->once())->method('load')->with($indexId, 'indexer_id')->will($this->returnSelf());
        $stateMock->expects($this->never())->method('setIndexerId');
        $stateMock->expects($this->once())->method('getId')->will($this->returnValue(1));
        $stateMock->expects($this->exactly(2))->method('setStatus')->will($this->returnSelf());
        $stateMock->expects($this->once())->method('getStatus')->will($this->returnValue('idle'));
        $stateMock->expects($this->exactly(2))->method('save')->will($this->returnSelf());
        $this->stateFactoryMock->expects($this->once())->method('create')->will($this->returnValue($stateMock));

        $this->viewMock->expects($this->once())->method('isEnabled')->will($this->returnValue(false));
        $this->viewMock->expects($this->never())->method('suspend');
        $this->viewMock->expects($this->once())->method('resume');

        $actionMock = $this->createPartialMock(
            ActionInterface::class,
            ['executeFull', 'executeList', 'executeRow']
        );
        $actionMock->expects($this->once())->method('executeFull')->will(
            $this->returnCallback(
                function () {
                    throw new \Error('Test Engine Error');
                }
            )
        );
        $this->actionFactoryMock->expects(
            $this->once()
        )->method(
            'create'
        )->with(
            'Some\Class\Name'
        )->will(
            $this->returnValue($actionMock)
        );

        $this->model->reindexAll();
    }

    /**
     * @return array
     */
    protected function getIndexerData()
    {
        return [
            'indexer_id' => 'indexer_internal_name',
            'view_id' => 'view_test',
            'action_class' => 'Some\Class\Name',
            'title' => 'Indexer public name',
            'description' => 'Indexer public description'
        ];
    }

    /**
     * @param $indexId
     */
    protected function loadIndexer($indexId)
    {
        $this->configMock->expects(
            $this->once()
        )->method(
            'getIndexer'
        )->with(
            $indexId
        )->will(
            $this->returnValue($this->getIndexerData())
        );
        $this->model->load($indexId);
    }

    public function testGetTitle()
    {
        $result = 'Test Result';
        $this->model->setTitle($result);
        $this->assertEquals($result, $this->model->getTitle());
    }

    public function testGetDescription()
    {
        $result = 'Test Result';
        $this->model->setDescription($result);
        $this->assertEquals($result, $this->model->getDescription());
    }

    public function testSetState()
    {
        $stateMock = $this->createPartialMock(
            State::class,
            ['loadByIndexer', 'getId', '__wakeup']
        );

        $this->model->setState($stateMock);
        $this->assertInstanceOf(State::class, $this->model->getState());
    }

    public function testIsScheduled()
    {
        $result = true;
        $this->viewMock->expects($this->once())->method('load')->will($this->returnSelf());
        $this->viewMock->expects($this->once())->method('isEnabled')->will($this->returnValue($result));
        $this->assertEquals($result, $this->model->isScheduled());
    }

    /**
     * @param bool $scheduled
     * @param string $method
     * @dataProvider setScheduledDataProvider
     */
    public function testSetScheduled($scheduled, $method)
    {
        $stateMock = $this->createPartialMock(State::class, ['load', 'save']);

        $this->stateFactoryMock->expects($this->once())->method('create')->will($this->returnValue($stateMock));
        $this->viewMock->expects($this->once())->method('load')->will($this->returnSelf());
        $this->viewMock->expects($this->once())->method($method)->will($this->returnValue(true));
        $stateMock->expects($this->once())->method('save')->will($this->returnSelf());
        $this->model->setScheduled($scheduled);
    }

    /**
     * @return array
     */
    public function setScheduledDataProvider()
    {
        return [
            [true, 'subscribe'],
            [false, 'unsubscribe']
        ];
    }

    public function testGetStatus()
    {
        $status = StateInterface::STATUS_WORKING;
        $stateMock = $this->createPartialMock(State::class, ['load', 'getStatus']);

        $this->stateFactoryMock->expects($this->once())->method('create')->will($this->returnValue($stateMock));
        $stateMock->expects($this->once())->method('getStatus')->will($this->returnValue($status));
        $this->assertEquals($status, $this->model->getStatus());
    }

    /**
     * @param string $method
     * @param string $status
     * @dataProvider statusDataProvider
     */
    public function testStatus($method, $status)
    {
        $stateMock = $this->createPartialMock(State::class, ['load', 'getStatus']);

        $this->stateFactoryMock->expects($this->once())->method('create')->will($this->returnValue($stateMock));
        $stateMock->expects($this->once())->method('getStatus')->will($this->returnValue($status));
        $this->assertEquals(true, $this->model->$method());
    }

    /**
     * @return array
     */
    public function statusDataProvider()
    {
        return [
            ['isValid', StateInterface::STATUS_VALID],
            ['isInvalid', StateInterface::STATUS_INVALID],
            ['isWorking', StateInterface::STATUS_WORKING]
        ];
    }

    public function testInvalidate()
    {
        $stateMock = $this->createPartialMock(
            State::class,
            ['load', 'setStatus', 'save']
        );

        $this->stateFactoryMock->expects($this->once())->method('create')->will($this->returnValue($stateMock));
        $stateMock->expects($this->once())->method('setStatus')->with(StateInterface::STATUS_INVALID)->will(
            $this->returnSelf()
        );
        $stateMock->expects($this->once())->method('save')->will($this->returnSelf());
        $this->model->invalidate();
    }

    public function testReindexRow()
    {
        $id = 1;

        $stateMock = $this->createPartialMock(State::class, ['load', 'save']);
        $actionMock = $this->createPartialMock(
            ActionInterface::class,
            ['executeFull', 'executeList', 'executeRow']
        );

        $this->actionFactoryMock->expects(
            $this->once()
        )->method(
            'create'
        )->will(
            $this->returnValue($actionMock)
        );

        $this->stateFactoryMock->expects($this->once())->method('create')->will($this->returnValue($stateMock));
        $stateMock->expects($this->once())->method('save')->will($this->returnSelf());
        $actionMock->expects($this->once())->method('executeRow')->with($id)->will($this->returnSelf());
        $this->model->reindexRow($id);
    }

    public function testReindexList()
    {
        $ids = [1];

        $stateMock = $this->createPartialMock(State::class, ['load', 'save']);
        $actionMock = $this->createPartialMock(
            ActionInterface::class,
            ['executeFull', 'executeList', 'executeRow']
        );

        $this->actionFactoryMock->expects(
            $this->once()
        )->method(
            'create'
        )->will(
            $this->returnValue($actionMock)
        );

        $this->stateFactoryMock->expects($this->once())->method('create')->will($this->returnValue($stateMock));
        $stateMock->expects($this->once())->method('save')->will($this->returnSelf());
        $actionMock->expects($this->once())->method('executeList')->with($ids)->will($this->returnSelf());
        $this->model->reindexList($ids);
    }
}
