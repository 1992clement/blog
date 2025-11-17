<?php

namespace App\Tests\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManager;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class RegistrationControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    private UserRepository $userRepository;

    private UserPasswordHasherInterface $passwordHasher;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        // Ensure we have a clean database
        $container = static::getContainer();

        $this->userRepository = $container->get(UserRepository::class);
        $this->passwordHasher = $container->get(UserPasswordHasherInterface::class);

        /** @var EntityManager $em */
        $em = $container->get('doctrine')->getManager();
        foreach ($this->userRepository->findAll() as $user) {
            $em->remove($user);
        }

        $em->flush();
    }

    public function testRegister(): void
    {
        $this->client->request('GET', '/register');
        self::assertResponseIsSuccessful();
        self::assertPageTitleContains('Register');

        $this->client->submitForm('Register', [
            'registration_form[username]' => 'Robert',
            'registration_form[email]' => 'me@example.com',
            'registration_form[password][first]' => 'password',
            'registration_form[password][second]' => 'password',
        ]);
        // Ensure redirection & user creation
        self::assertResponseRedirects('/');
        $this->assertTrue($this->isAuthenticated());

        $users = $this->userRepository->findAll();
        self::assertCount(1, $users);

        $user = $users[0];
        $this->assertUserData($user);

        // Ensure the verification email was sent
        self::assertEmailCount(1);
        self::assertCount(1, $messages = $this->getMailerMessages());
        self::assertEmailAddressContains($messages[0], 'from', 'registration@clementtrumpff.com');
        self::assertEmailAddressContains($messages[0], 'to', 'me@example.com');
        self::assertEmailTextBodyContains($messages[0], 'This link will expire in 1 hour.');

        $this->client->followRedirect();
        $this->assertTrue($this->isAuthenticated());

        // Get the verification link from the email
        /** @var TemplatedEmail $templatedEmail */
        $templatedEmail = $messages[0];
        $messageBody = $templatedEmail->getHtmlBody();
        self::assertIsString($messageBody);

        preg_match('#(http://localhost/verify/email.+)">#', $messageBody, $resetLink);

        // "Click" the link and see if the user is verified
        $this->client->request('GET', $resetLink[1]);
        $this->client->followRedirect();

        self::assertTrue(static::getContainer()->get(UserRepository::class)->findAll()[0]->isVerified());
    }

    private function assertUserData(User $user): void
    {
        self::assertSame('Robert', $user->getUsername());
        self::assertSame('me@example.com', $user->getEmail());
        self::assertTrue($this->passwordHasher->isPasswordValid($user, 'password'));
        self::assertSame(['ROLE_USER'], $user->getRoles());
        self::assertEmpty($user->getPresentation());
        $this->assertEqualsWithDelta(
            time(),
            $user->getRegistrationDate()->getTimestamp(),
            5
        );

        $this->assertEqualsWithDelta(
            time(),
            $user->getLastLoginDate()->getTimestamp(),
            5
        );
        self::assertFalse($user->isVerified());
    }

    private function isAuthenticated(): bool
    {
        $token = static::getContainer()->get('security.token_storage')->getToken();
        return $token !== null && $token->getUser() instanceof User;
    }
}
