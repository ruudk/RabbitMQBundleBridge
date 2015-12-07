<?php

namespace SimpleBus\RabbitMQBundleBridge\Tests;

use Exception;
use OldSound\RabbitMqBundle\RabbitMq\ConsumerInterface;
use PhpAmqpLib\Message\AMQPMessage;
use SimpleBus\RabbitMQBundleBridge\Event\Events;
use SimpleBus\RabbitMQBundleBridge\Event\MessageConsumed;
use SimpleBus\RabbitMQBundleBridge\Event\MessageConsumptionFailed;
use SimpleBus\RabbitMQBundleBridge\RabbitMQMessageConsumer;

class RabbitMQMessageConsumerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @test
     */
    public function it_consumes_the_message_body_as_a_serialized_envelope()
    {
        $serializedEnvelope = 'a serialized envelope';
        $message = $this->newAMQPMessage($serializedEnvelope);
        $serializedEnvelopeConsumer = $this->mockSerializedEnvelopeConsumer();
        $serializedEnvelopeConsumer
            ->expects($this->once())
            ->method('consume')
            ->with($serializedEnvelope);

        $eventDispatcher = $this->eventDispatcherDispatchesMessageConsumedEvent($message);
        $consumer = new RabbitMQMessageConsumer($serializedEnvelopeConsumer, $eventDispatcher);

        $result = $consumer->execute($message);
        $this->assertSame(true, $result);
    }

    /**
     * @test
     */
    public function it_handles_an_error_but_throws_no_exception_if_consuming_the_message_fails()
    {
        $exception = new Exception('I always fail');
        $serializedEnvelopeConsumer = $this->mockSerializedEnvelopeConsumer();
        $serializedEnvelopeConsumer
            ->expects($this->once())
            ->method('consume')
            ->will($this->throwException($exception));
        $message = $this->newAMQPMessage();

        $eventDispatcher = $this->eventDispatcherDispatchesConsumptionFailedEvent($message, $exception);

        $consumer = new RabbitMQMessageConsumer($serializedEnvelopeConsumer, $eventDispatcher);

        $result = $consumer->execute($message);
        $this->assertSame(false, $result);
    }

    private function newAMQPMessage($messageBody = '')
    {
        return new AMQPMessage($messageBody);
    }

    private function mockSerializedEnvelopeConsumer()
    {
        return $this->getMock('SimpleBus\Asynchronous\Consumer\SerializedEnvelopeConsumer');
    }

    private function eventDispatcherDispatchesConsumptionFailedEvent(AMQPMessage $message, Exception $exception)
    {
        $eventDispatcher = $this->getMock('Symfony\Component\EventDispatcher\EventDispatcherInterface');

        $eventDispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->will($this->returnCallback(function ($name, MessageConsumptionFailed $event) use ($message, $exception) {
                $this->assertSame(Events::MESSAGE_CONSUMPTION_FAILED, $name);
                $this->assertSame($message, $event->message());
                $this->assertSame($exception, $event->exception());
            }));

        return $eventDispatcher;
    }

    private function eventDispatcherDispatchesMessageConsumedEvent($message)
    {
        $eventDispatcher = $this->getMock('Symfony\Component\EventDispatcher\EventDispatcherInterface');

        $eventDispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->will($this->returnCallback(function ($name, MessageConsumed $event) use ($message) {
                $this->assertSame(Events::MESSAGE_CONSUMED, $name);
                $this->assertSame($message, $event->message());
            }));

        return $eventDispatcher;
    }
}
