<?php

namespace DealerInspire\Operations\Tests;

use Carbon\Carbon;
use Illuminate\Support\Facades\Queue;
use DealerInspire\Operations\OperationJob;
use DealerInspire\Operations\Facades\Operator;
use DealerInspire\Operations\Tests\Operations\AnotherOperation;
use DealerInspire\Operations\Tests\Operations\ExampleOperation;

class OperatorTest extends TestCase
{
    /** @test */
    public function it_queues_operations_that_are_ready_to_run()
    {
        $exampleOperation = new ExampleOperation();
        $exampleOperation->should_run_at = Carbon::now()->subMinutes(5);
        $exampleOperation->save();

        Operator::queue();

        Queue::assertPushed(OperationJob::class, function (OperationJob $job) use ($exampleOperation) {
            return $job->getOperation()->is($exampleOperation);
        });

        $this->assertNotNull($exampleOperation->fresh()->started_run_at);
    }

    /** @test */
    public function it_queues_multiple_operations_that_are_ready_to_run()
    {
        $exampleOperation = new ExampleOperation();
        $exampleOperation->should_run_at = Carbon::now()->subMinutes(5);
        $exampleOperation->save();

        $firstAnotherOperation = new AnotherOperation();
        $firstAnotherOperation->should_run_at = Carbon::now()->subMinutes(9);
        $firstAnotherOperation->save();

        $secondAnotherOperation = new AnotherOperation();
        $secondAnotherOperation->should_run_at = Carbon::now();
        $secondAnotherOperation->save();

        Operator::queue();

        Queue::assertPushed(OperationJob::class, function (OperationJob $job) use ($exampleOperation) {
            return $job->getOperation()->is($exampleOperation);
        });
        Queue::assertPushed(OperationJob::class, function (OperationJob $job) use ($firstAnotherOperation) {
            return $job->getOperation()->is($firstAnotherOperation);
        });
        Queue::assertPushed(OperationJob::class, function (OperationJob $job) use ($secondAnotherOperation) {
            return $job->getOperation()->is($secondAnotherOperation);
        });

        $this->assertNotNull($exampleOperation->fresh()->started_run_at);
        $this->assertNotNull($firstAnotherOperation->fresh()->started_run_at);
        $this->assertNotNull($secondAnotherOperation->fresh()->started_run_at);
    }

    /** @test */
    public function it_does_not_queue_operations_that_are_not_ready_to_run()
    {
        $exampleOperation = new ExampleOperation();
        $exampleOperation->should_run_at = Carbon::now()->addMinutes(5);
        $exampleOperation->save();

        Operator::queue();

        Queue::assertNothingPushed();

        $this->assertNull($exampleOperation->fresh()->started_run_at);
    }

    /** @test */
    public function it_does_not_queue_operations_that_have_already_started_to_run()
    {
        $exampleOperation = new ExampleOperation();
        $exampleOperation->should_run_at = Carbon::now()->subMinutes(5);
        $exampleOperation->started_run_at = Carbon::now()->subMinute();
        $exampleOperation->save();

        Operator::queue();

        Queue::assertNothingPushed();

        $this->assertEquals(Carbon::now()->subMinute(), $exampleOperation->fresh()->started_run_at);
    }

    /** @test */
    public function it_does_not_queue_operations_that_have_been_deleted()
    {
        $exampleOperation = new ExampleOperation();
        $exampleOperation->should_run_at = Carbon::now()->subMinutes(5);
        $exampleOperation->deleted_at = Carbon::now()->subMinute();
        $exampleOperation->save();

        Operator::queue();

        Queue::assertNothingPushed();

        $this->assertNull($exampleOperation->fresh()->started_run_at);
    }
}
