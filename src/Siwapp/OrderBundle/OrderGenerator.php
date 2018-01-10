<?php

namespace Siwapp\OrderBundle;

use Doctrine\ORM\EntityManager;
use Siwapp\CoreBundle\Entity\Item;
use Siwapp\OrderBundle\Entity\Order;
use Siwapp\EstimateBundle\Entity\Estimate;

class OrderGenerator
{
    protected $em;

    public function __construct(EntityManager $em)
    {
        $this->em = $em;
    }

    public function generate(Order $order)
    {
        $estimate = new Estimate;
        $estimate->setCustomerName($order->getCustomerName());
        $estimate->setCustomerEmail($order->getCustomerEmail());
        $estimate->setCustomerIdentification($order->getCustomerIdentification());
        $estimate->setContactPerson($order->getContactPerson());
        $estimate->setInvoicingAddress($order->getInvoicingAddress());
        $estimate->setShippingAddress($order->getShippingAddress());
        $estimate->setSeries($order->getSeries());
        foreach ($order->getItems() as $item) {
            $estimateItem = new Item;
            $estimateItem->setDescription($item->getDescription());
            $estimateItem->setQuantity($item->getQuantity());
            $estimateItem->setDiscount($item->getDiscount());
            $estimateItem->setUnitaryCost($item->getUnitaryCost());
            foreach ($item->getTaxes() as $tax) {
                $estimateItem->addTax($tax);
            }
            $estimate->addItem($estimateItem);
        }
        $estimate->setNotes($order->getNotes());
        $estimate->setTerms($order->getTerms());

        $this->em->persist($estimate);
        $this->em->flush();

        return $order;
    }
}
