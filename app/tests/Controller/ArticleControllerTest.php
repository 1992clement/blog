<?php

namespace App\Tests\Controller;

use App\Entity\Article;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

final class ArticleControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    private EntityManagerInterface $manager;

    private EntityRepository $articleRepository;

    private EntityRepository $userRepository;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->manager = static::getContainer()->get('doctrine')->getManager();
        $this->articleRepository = $this->manager->getRepository(Article::class);
        $this->userRepository = $this->manager->getRepository(User::class);

        foreach ($this->articleRepository->findAll() as $object) {
            $this->manager->remove($object);
        }

        $this->manager->flush();
    }

    public function testIndex(): void
    {
        $this->client->followRedirects();
        $this->client->request('GET', '/article');

        $this->assertResponseStatusCodeSame(200);
        $this->assertPageTitleContains('Article index');

        //@TODO: Use the $crawler to perform additional assertions e.g.
        // $this->assertSame('Some text on the page', $crawler->filter('.p')->first()->text());
    }

    public function testNew(): void
    {
        /** @var User $admin */
        $admin = $this->userRepository->findOneBy([
            'username' => 'admin',
        ]);
        $this->client->loginUser($admin);
        $this->client->request('GET', '/article/new');

        $this->client->submitForm('Save', [
            'article[title]' => 'Testing',
            'article[content]' => 'Testing',
        ]);

        /** @var Article[] $articles */
        $articles = $this->articleRepository->findAll();
        $this->assertCount(1, $articles);
        $article = $articles[0];
        $this->assertSame('Testing', $article->getTitle());
        $this->assertSame('Testing', $article->getContent());
        $this->assertSame($admin->getId(), $article->getCreator()->getId());
        $this->assertEqualsWithDelta(
            time(),
            $article->getCreationDate()->getTimestamp(),
            5
        );
        $this->assertEqualsWithDelta(
            time(),
            $article->getModificationDate()->getTimestamp(),
            5
        );

        $this->assertResponseRedirects(sprintf('/article/%s/show', $article->getId()));
    }

    public function testNewRejectsUsersWithoutCredentials(): void
    {
        $this->client->request('GET', '/article/new');
        $this->assertResponseRedirects('/login');
    }

    public function testShow(): void
    {
        /** @var User $admin */
        $admin = $this->userRepository->findOneBy([
            'username' => 'admin',
        ]);
        $article = $this->createArticle($admin);

        $this->client->request('GET', sprintf('/article/%s/show', $article->getId()));

        $this->assertResponseStatusCodeSame(200);
        $this->assertPageTitleContains('Article');

        $crawler = $this->client->getCrawler();
        $tdList = $crawler->filter('td');
        $expectedData = [
            (string) $article->getId(),
            $article->getTitle(),
            $article->getContent(),
            $article->getCreationDate()->format('Y-m-d H:i:s'),
            $article->getModificationDate()->format('Y-m-d H:i:s'),
            $article->getCreator()->getUsername(),
        ];
        $htmlContent = $tdList->each(function (Crawler $node) {
            return $node->text();
        });

        $this->assertSame($expectedData, $htmlContent);
    }

    public function testEdit(): void
    {
        /** @var User $admin */
        $admin = $this->userRepository->findOneBy([
            'username' => 'admin',
        ]);
        $article = $this->createArticle($admin);

        $this->client->loginUser($admin);
        $this->client->request('GET', sprintf('/article/%s/edit', $article->getId()));

        $this->client->submitForm('Update', [
            'article[title]' => 'New Title',
            'article[content]' => 'New Content',
        ]);

        $this->assertResponseRedirects('/article');

        /** @var Article[] $articleUpdated */
        $articleUpdated = $this->articleRepository->findAll();

        $this->assertSame('New Title', $articleUpdated[0]->getTitle());
        $this->assertSame('New Content', $articleUpdated[0]->getContent());
        $this->assertSame($article->getCreationDate()->getTimestamp(), $articleUpdated[0]->getCreationDate()->getTimestamp());
        $this->assertGreaterThan($article->getModificationDate(), $articleUpdated[0]->getModificationDate());
        $this->assertSame($article->getCreator()->getId(), $articleUpdated[0]->getCreator()->getId());
    }

    public function testEditRejectsUsersWithoutCredentials(): void
    {
        /** @var User $admin */
        $admin = $this->userRepository->findOneBy([
            'username' => 'admin',
        ]);
        $article = $this->createArticle($admin);

        $this->client->request('GET', sprintf('/article/%s/edit', $article->getId()));
        $this->assertResponseRedirects('/login');
    }

    public function testDelete(): void
    {
        /** @var User $admin */
        $admin = $this->userRepository->findOneBy([
            'username' => 'admin',
        ]);
        $article = $this->createArticle($admin);

        $this->client->loginUser($admin);
        $this->client->request('GET', sprintf('/article/%s/show', $article->getId()));
        $this->client->submitForm('Delete');

        $this->assertResponseRedirects('/article');
        $this->assertSame(0, $this->articleRepository->count([]));
    }

    public function testDeleteRejectsUsersWithoutCredentials(): void
    {
        /** @var User $admin */
        $admin = $this->userRepository->findOneBy([
            'username' => 'admin',
        ]);
        $article = $this->createArticle($admin);

        $this->client->request('POST', sprintf('/article/%s/delete', $article->getId()));
        $this->assertResponseRedirects('/login');
    }

    private function createArticle(User $creator): Article
    {
        $article = new Article();
        $article->setTitle('My Title');
        $article->setContent('My content');
        $article->setCreationDate(new \DateTimeImmutable());
        $article->setModificationDate(new \DateTimeImmutable());
        $article->setCreator($creator);

        $this->manager->persist($article);
        $this->manager->flush();

        return $article;
    }
}
