<?php

namespace App\Tests;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class SecurityControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $container = static::getContainer();
        $em = $container->get('doctrine.orm.entity_manager');
        $userRepository = $em->getRepository(User::class);

        // Remove any existing users from the test database
        foreach ($userRepository->findAll() as $user) {
            $em->remove($user);
        }

        $em->flush();

        // Create a User fixture
        /** @var UserPasswordHasherInterface $passwordHasher */
        $passwordHasher = $container->get('security.user_password_hasher');

        $user = (new User())
            ->setEmail('email@example.com')
            ->setUsername('username')
            ->setLastLoginDate(new \DateTimeImmutable())
            ->setRegistrationDate(new \DateTimeImmutable());
        $user->setPassword($passwordHasher->hashPassword($user, 'password'));

        $em->persist($user);
        $em->flush();
    }

    public function testLogin(): void
    {
        $this->client->request('GET', '/login');
        self::assertResponseIsSuccessful();

        $this->client->submitForm('Sign in', [
            '_username' => 'username',
            '_password' => 'password',
        ]);

        $session = $this->client->getRequest()->getSession();

        $authError = $session->get('_security.last_error');
        if ($authError) {
            $this->fail($authError->getMessage());
        }
        $this->assertTrue($this->isAuthenticated());

        self::assertResponseRedirects('/');
        $this->client->followRedirect();

        self::assertSelectorNotExists('.alert-danger');
        self::assertResponseIsSuccessful();
    }

    public function testLoginFailsIfEmailInvalid(): void
    {
        $this->client->request('GET', '/login');
        self::assertResponseIsSuccessful();

        $this->client->submitForm('Sign in', [
            '_username' => 'doesNotExist',
            '_password' => 'password',
        ]);

        self::assertResponseRedirects('/login');
        $this->client->followRedirect();

        // Ensure we do not reveal if the user exists or not.
        self::assertSelectorTextContains('.alert-danger', 'Invalid credentials.');
    }

    public function testLoginFailsIfPasswordInvalid(): void
    {
        $this->client->request('GET', '/login');
        self::assertResponseIsSuccessful();

        $this->client->submitForm('Sign in', [
            '_username' => 'username',
            '_password' => 'bad-password',
        ]);

        self::assertResponseRedirects('/login');
        $this->client->followRedirect();

        // Ensure we do not reveal the user exists but the password is wrong.
        self::assertSelectorTextContains('.alert-danger', 'Invalid credentials.');
    }

    public function testLogout(): void
    {
        $user = static::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'email@example.com']);
        $this->client->loginUser($user);
        $this->assertTrue($this->isAuthenticated());

        $this->client->request('GET', '/logout');

        self::assertResponseRedirects('/');
        $this->assertFalse($this->isAuthenticated());

    }

    private function isAuthenticated(): bool
    {
        $token = static::getContainer()->get('security.token_storage')->getToken();
        return $token !== null && $token->getUser() instanceof User;
    }
}
