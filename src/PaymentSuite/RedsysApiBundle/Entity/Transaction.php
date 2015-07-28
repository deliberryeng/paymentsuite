<?php

/*
 * This file is part of the Elcodi package.
 *
 * Copyright (c) 2015 Project
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * Feel free to edit as you please, and have fun.
 *
 * @author
 */

namespace PaymentSuite\RedsysApiBundle\Entity;

class Transaction
{
    /**
     * @var int
     */
    protected $id;

    /**
     * @var string
     *
     * Order if from the payment client
     */
    protected $orderId;

    /**
     * @var integer
     *
     * Transaction amount in cents
     */
    protected $amount;

    /**
     * @var int
     *
     * 0: Request
     * 1: Response
     */
    protected $transactionType;

    /**
     * @var string
     */
    protected $returnCode;

    /**
     * @var string
     */
    protected $errorCode;

    /**
     * @var string
     */
    protected $authorizationCode;

    /**
     * @var string
     */
    protected $message;

    /**
     * @var \DateTime
     */
    protected $createdAt;

    /**
     * @param $orderId
     * @param $amount
     * @param $transactionType
     * @param $returnCode
     * @param $errorCode
     * @param $authorizationCode
     * @param $message
     */
    function __construct(
        $orderId,
        $amount,
        $transactionType,
        $returnCode,
        $errorCode,
        $authorizationCode,
        $message)
    {
        $this->orderId = $orderId;
        $this->amount = $amount;
        $this->transactionType = $transactionType;
        $this->returnCode = $returnCode;
        $this->errorCode = $errorCode;
        $this->authorizationCode = $authorizationCode;
        $this->message = $message;

        $this->createdAt = new \DateTime();
    }


    /**
     * @return string
     */
    public function getOrderId()
    {
        return $this->orderId;
    }

    /**
     * @return int
     */
    public function getAmount()
    {
        return $this->amount;
    }

    /**
     * @return int
     */
    public function getTransactionType()
    {
        return $this->transactionType;
    }

    /**
     * @return string
     */
    public function getReturnCode()
    {
        return $this->returnCode;
    }

    /**
     * @return string
     */
    public function getErrorCode()
    {
        return $this->errorCode;
    }

    /**
     * @return string
     */
    public function getAuthorizationCode()
    {
        return $this->authorizationCode;
    }

    /**
     * @return string
     */
    public function getMessage()
    {
        return $this->message;
    }

}