<?php

namespace Pug\Test;

use PHPUnit\Framework\TestCase;

class AbstractTestCase extends TestCase
{
    protected $_expectedException;
    protected $_expectedExceptionMessage;
    protected $_expectedExceptionCode;

    public function expectException($exception)
    {
        if (method_exists(TestCase::class, 'expectException')) {
            parent::expectException($exception);

            return;
        }

        $this->_expectedException = $exception;

        $this->setExpectedException(
            $this->_expectedException ?: \Exception::class,
            $this->_expectedExceptionMessage ?: '',
            $this->_expectedExceptionCode
        );
    }

    public function expectExceptionMessage($message)
    {
        if (method_exists(TestCase::class, 'expectException')) {
            parent::expectExceptionMessage($message);

            return;
        }

        $this->_expectedExceptionMessage = $message;

        $this->setExpectedException(
            $this->_expectedException ?: \Exception::class,
            $this->_expectedExceptionMessage ?: '',
            $this->_expectedExceptionCode
        );
    }

    public function expectExceptionCode($code)
    {
        if (method_exists(TestCase::class, 'expectException')) {
            parent::expectExceptionMessage($code);

            return;
        }

        $this->_expectedExceptionCode = $code;

        $this->setExpectedException(
            $this->_expectedException ?: \Exception::class,
            $this->_expectedExceptionMessage ?: '',
            $this->_expectedExceptionCode
        );
    }
}
