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

namespace PaymentSuite\RedsysApiBundle\Repository;

use Doctrine\ORM\EntityRepository;

class TransactionRepository extends EntityRepository
{
    /**
     * Gets all transactions between two dates
     *
     * @param \DateTime $startDate
     * @param \DateTime $endDate
     * @return mixed
     */
    public function findAllCreatedAtBetween(\DateTime $startDate, \DateTime $endDate)
    {
        return $this->_em->createQueryBuilder()
            ->select('t')
            ->from('PaymentSuite\RedsysApiBundle\Entity\Transaction', 't')
            ->where('t.createdAt BETWEEN :startDate AND :endDate')
           ->setParameter('startDate', $startDate->format('Y-m-d h:i:s'))
           ->setParameter('endDate', $endDate->format('Y-m-d  h:i:s'))
            ->getQuery()
            ->getResult();
    }
}