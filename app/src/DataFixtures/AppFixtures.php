<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(
        private UserPasswordHasherInterface $userPasswordHasher
    ) {

    }

    public function load(ObjectManager $manager): void
    {
        $admin = (new User())
            ->setRoles(['ROLE_ADMIN'])
            ->setUsername('admin')
            ->setEmail('admin@admin.com')
            ->setRegistrationDate(new \DateTimeImmutable())
            ->setLastLoginDate(new \DateTimeImmutable());
        $admin->setPassword($this->userPasswordHasher->hashPassword($admin, 'azerty'));
        $manager->persist($admin);

        $user = (new User())
            ->setUsername('user')
            ->setEmail('user@user.com')
            ->setRegistrationDate(new \DateTimeImmutable())
            ->setLastLoginDate(new \DateTimeImmutable());
        $user->setPassword($this->userPasswordHasher->hashPassword($user, 'azerty'));
        $manager->persist($user);

        $manager->flush();
    }
}
