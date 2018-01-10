<?php

namespace Siwapp\OrderBundle\Form;

use Siwapp\CoreBundle\Form\AbstractInvoiceType;
use Siwapp\OrderBundle\Entity\Order;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class OrderType extends AbstractInvoiceType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        parent::buildForm($builder, $options);

        $builder
            ->add('issue_date', DateType::class, [
                'widget' => 'single_text',
                'label' => 'form.issue_date',
                'translation_domain' => 'SiwappOrderBundle',
            ])
            ->add('sent_by_email', null, [
                'label' => 'form.sent_by_email',
                'translation_domain' => 'SiwappInvoiceBundle',
            ])
            ->add('imported', HiddenType::class, [
                'attr' => ['value' => '0'],
                'label' => false,
                'translation_domain' => 'SiwappOrderBundle',
            ])
        ;

        $choices = array(
            'order.pending' => Order::PENDING,
            'order.approved' => Order::APPROVED,
            'order.rejected' => Order::REJECTED
        );

        if ( $builder->getData()->isDraft() )
        {
            $choices = array_merge(array('' => Order::DRAFT), $choices);
        }

        //if (!$builder->getData()->isDraft()) {
        if ($builder->getData()->getNumber() > 0) {
            $builder->add('status', ChoiceType::class, [
                'label' => 'form.status',
                'translation_domain' => 'SiwappOrderBundle',
                'choices' => $choices,
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setRequired([
            'editing'
        ]);

        $resolver->setDefaults([
            'data_class' => 'Siwapp\OrderBundle\Entity\Order',
            'editing' => false,
        ]);
    }
}
