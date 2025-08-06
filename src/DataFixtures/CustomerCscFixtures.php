<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Shop\CustomerCsc;
use App\Entity\User\ShopUser;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class CustomerCscFixtures extends Fixture implements ContainerAwareInterface
{
    private ?ContainerInterface $container = null;

    public function setContainer(?ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    public function load(ObjectManager $manager): void
    {
        $jsonFile = __DIR__ . '/data/customer_csc.json';
        $cscData = json_decode(file_get_contents($jsonFile), true);

        // Get a test user or create one if needed
        $userRepository = $manager->getRepository(ShopUser::class);
        $user = $userRepository->findOneBy([]);

        if (!$user) {
            // Create a test user if none exists
            $user = new ShopUser();
            $user->setEmail('test@example.com');
            $user->setUsername('test_user');
            $user->setPlainPassword('test123');
            $user->setEnabled(true);
            $manager->persist($user);
        }

        // Create CustomerCsc for the user
        $customerCsc = new CustomerCsc();
        $customerCsc->setUser($user);
        $customerCsc->setCscs($cscData);

        $manager->persist($customerCsc);
        $manager->flush();
    }
}
