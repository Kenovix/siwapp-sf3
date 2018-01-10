<?php

namespace Siwapp\OrderBundle\Form;

use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

use Siwapp\EstimateBundle\Entity\Estimate;

class SearchOrderType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('terms', null, [
                'required' => false,
                'label' => 'search.terms',
                'translation_domain' => 'SiwappOrderBundle',
            ])
            ->add('status', ChoiceType::class, [
                'choices' => [
                    'order.draft' => Estimate::DRAFT,
                    'order.pending' => Estimate::PENDING,
                    'order.approved' => Estimate::APPROVED,
                    'order.rejected' => Estimate::REJECTED,
                ],
                'required' => false,
                'label' => 'search.status',
                'translation_domain' => 'SiwappOrderBundle',
            ])
            ->add('date_from', DateType::class, [
                'widget' => 'single_text',
                'required' => false,
                'label' => 'search.date_from',
                'translation_domain' => 'SiwappOrderBundle',
            ])
            ->add('date_to', DateType::class, [
                'widget' => 'single_text',
                'required' => false,
                'label' => 'search.date_to',
                'translation_domain' => 'SiwappOrderBundle',
            ])
            ->add('customer', null, [
                'required' => false,
                'label' => 'search.customer',
                'translation_domain' => 'SiwappOrderBundle',
            ])
            ->add('tags', TextType::class, [
                'attr' => ['class' => 'no-tagsinput'],
                'label' => 'search.tag',
                'translation_domain' => 'SiwappOrderBundle',
                'required' => false,
            ])
        ;

        $builder->add('series', EntityType::class, array(
            'class' => 'SiwappCoreBundle:Series',
            'choice_label' => 'name',
            'required' => false,
            'label' => 'search.series',
            'translation_domain' => 'SiwappOrderBundle',
        ));
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'csrf_protection' => false,
        ));
    }
}
