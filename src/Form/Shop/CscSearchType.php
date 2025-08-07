<?php

declare(strict_types=1);

namespace App\Form\Shop;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CscSearchType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('statut', ChoiceType::class, [
                'label' => 'Statut',
                'required' => false,
                'placeholder' => 'Tous les statuts',
                'choices' => [
                    'Ouverte' => 'Ouverte',
                    'Clôturée' => 'Clôturée',
                    'En cours' => 'En cours'
                ],
                'attr' => ['class' => 'ui dropdown']
            ])
            ->add('produit', ChoiceType::class, [
                'label' => 'Produit',
                'required' => false,
                'placeholder' => 'Tous les produits',
                'choices' => [],  // À remplir avec les produits disponibles
                'attr' => ['class' => 'ui dropdown']
            ])
            ->add('referenceProduit', TextType::class, [
                'label' => 'Référence produit',
                'required' => false,
                'attr' => [
                    'class' => 'ui input',
                    'placeholder' => 'Rechercher par référence produit...'
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection' => false,
        ]);
    }
}
