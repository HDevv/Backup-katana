<?php

declare(strict_types=1);

namespace App\Form\Shop;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\File;

class CscFileUploadType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('files', FileType::class, [
                'label' => 'Joindre des fichiers',
                'multiple' => true,
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'class' => 'csc-file-input',
                    'accept' => '.pdf,.jpg,.jpeg,.png,.gif',
                    'data-max-size' => '10485760', // 10MB
                ],
                'constraints' => [
                    new All([
                        new File([
                            'maxSize' => '10M',
                            'mimeTypes' => [
                                'application/pdf',
                                'image/jpeg',
                                'image/jpg',
                                'image/png',
                                'image/gif',
                            ],
                            'mimeTypesMessage' => 'Veuillez télécharger un fichier PDF ou une image valide (JPG, PNG, GIF)',
                            'maxSizeMessage' => 'Le fichier ne doit pas dépasser {{ limit }}',
                        ])
                    ])
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection' => true,
        ]);
    }
}
