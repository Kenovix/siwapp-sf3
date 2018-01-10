<?php
/**
 * Created by PhpStorm.
 * User: jvasquez
 * Date: 16/1/2017
 * Time: 3:12 AM
 */

namespace Siwapp\OrderBundle\Form;

use Siwapp\OrderBundle\Entity\ExcelFile;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\FileType;

class ExcelType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('file', FileType::class, [
                'label' => false
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            //'data_class' => ExcelFile::class,
        ));
    }
}