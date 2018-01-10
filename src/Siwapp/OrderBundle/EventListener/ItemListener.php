<?php

namespace Siwapp\OrderBundle\EventListener;

use Doctrine\ORM\Event\OnFlushEventArgs;
use Siwapp\CoreBundle\Entity\Item;
use Siwapp\OrderBundle\Entity\Order;

class ItemListener
{
    public function onFlush(OnFlushEventArgs $args)
    {
        $em = $args->getEntityManager();
        $uow = $em->getUnitOfWork();
        $metadata = $em->getClassMetadata(Order::class);

        foreach ($uow->getScheduledEntityUpdates() as $entity) {
            if (!$entity instanceof Item) {
                continue;
            }

            $result = $em->getRepository(Order::class)->findByItem($entity);
            foreach ($result as $order) {
                $order->checkAmounts();
                $uow->recomputeSingleEntityChangeSet($metadata, $order);
            }
        }
    }
}
