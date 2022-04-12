<?php declare(strict_types=1);

namespace Tests\Becklyn\DeployMessageGenerator\SystemIntegrations\ChatSystems;

use Becklyn\DeployMessageGenerator\SystemIntegration\ChatSystems\SlackChatSystem;
use Becklyn\DeployMessageGenerator\SystemIntegration\TicketSystems\TicketInfo;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Notifier\Transport\NullTransport;

class SlackIntegrationTest extends TestCase
{
    static private SlackChatSystem $slack;

    public static function setUpBeforeClass () : void
    {
        parent::setUpBeforeClass();
        $io = new SymfonyStyle(new StringInput(""), new ConsoleOutput());
        self::$slack = new SlackChatSystem($io, "My-Token", "My-Deployment-Channel");
    }


    /**
     * @doesNotPerformAssertions
     */
    public function testMessageSending () : void
    {
        $tickets = [
            new TicketInfo('FOO-1', 'Goto google.com', 'https://google.com'),
            new TicketInfo('FOO-2', 'Goto github.com', 'https://github.com')
        ];

        $message = self::$slack->getChatMessageThread($tickets, 'Nowhere', 'deploy-message-generator-test', [], []);
        self::$slack->sendMessage($message[0], new NullTransport());
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testMessageSendingWithManyTickets () : void
    {
        $tickets = [];
        for ($i = 0; $i < 100; $i++)
        {
            $tickets[] = new TicketInfo("FOO-$i", "Goto google.com", "https://google.com");
        }

        $message = self::$slack->getChatMessageThread($tickets, "Nowhere", "deploy-message-generator-test", [], []);
        self::$slack->sendMessage($message[0], new NullTransport());
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testMessageSendingLongText () : void
    {
        // This is 3000+ characters
        $loremIpsum = 'Lorem ipsum dolor sit amet, consectetur adipisicing elit. Culpa cum cupiditate deserunt dignissimos dolores ea est, explicabo hic in inventore labore molestias nam similique tenetur voluptates. Neque nesciunt numquam recusandae tenetur totam. Architecto at aut consequuntur deserunt dolore doloremque dolorum earum expedita, labore laudantium molestias, pariatur, possimus quibusdam quos ratione repellat sint soluta veniam! Adipisci architecto beatae commodi consequatur deserunt dolor ducimus eius et eveniet exercitationem hic id, ipsum itaque iusto magnam maxime nesciunt nulla numquam obcaecati odio odit optio pariatur possimus qui quod recusandae repellat reprehenderit sapiente sequi sunt veritatis voluptas voluptatem voluptates? Aspernatur cumque eius explicabo ipsam quos rem sint suscipit voluptatum. Accusantium animi aperiam beatae blanditiis, cum delectus deserunt dolor doloremque et eveniet explicabo facilis fuga harum in ipsa iure labore laboriosam laudantium minima modi nemo nulla officia omnis praesentium quae quisquam quod sapiente tempora velit veniam! Adipisci alias autem consequatur, debitis delectus doloribus ea earum esse illum incidunt inventore iure laboriosam maiores, minima modi nesciunt nihil nostrum, nulla pariatur quaerat quasi quo quos recusandae repellendus rerum sapiente sint sit tempora ullam ut velit vitae voluptatem voluptates. Amet atque consequuntur cum cupiditate debitis ea eaque enim eos error esse eveniet fugit in inventore ipsa itaque magnam natus nostrum odit placeat provident quam, quibusdam quidem quis quisquam quo quod repudiandae sapiente sed velit veniam vitae voluptas voluptatem, voluptates? Corporis dicta quasi vero. Accusamus adipisci at ea exercitationem fugit incidunt obcaecati odio perferendis possimus praesentium. Consequatur earum labore modi odio quo reprehenderit, sequi! Ad aperiam architecto consequatur corporis cumque dicta ea eaque, explicabo hic laboriosam, minima porro quia rerum sint tenetur unde, vel. Distinctio dolore natus perferendis sed. Corporis cum labore ullam veritatis. Consequatur delectus deserunt distinctio dolore dolorem error esse ex fuga incidunt iusto, nesciunt quaerat quasi quis, sequi sit, soluta tempore veniam! Dolorem facilis quo tenetur voluptatum. Accusantium alias dolorem doloremque explicabo illo laborum magni mollitia, natus nulla porro quasi suscipit tenetur veritatis. Architecto at beatae, doloribus earum est excepturi facilis, incidunt iusto, labore libero magnam maiores molestias nulla officia omnis perspiciatis repudiandae sit! Ducimus, nesciunt, nihil. Ab, eos esse excepturi magnam mollitia numquam tenetur. A accusantium amet, animi aspernatur at culpa cupiditate dicta distinctio dolor dolorem, eaque eius eligendi, et exercitationem facere hic iusto laborum minima modi molestiae odit pariatur perspiciatis praesentium quo rem repellat sapiente tempore temporibus totam veniam. Aliquam debitis eveniet expedita odit tempore. Accusantium aut dolorem et omnis quibusdam recusandae voluptate? Accusamus aliquam atque aut et fugit laboriosam laborum omnis, optio perspiciatis qui quod, repellat sed sunt temporibus totam ullam ut vel. Ab architecto asperiores assumenda commodi cumque cupiditate delectus dolor dolorem ea eveniet facere fugit impedit incidunt iste iusto, magnam non numquam odio optio porro praesentium provident quasi qui quo repellat repudiandae, rerum saepe sint soluta temporibus voluptate, voluptates voluptatibus voluptatum. A ab ad aperiam beatae blanditiis cupiditate deleniti deserunt distinctio doloremque ducimus ea esse excepturi facilis id, iure minima necessitatibus nobis optio praesentium, provident quibusdam rem voluptates? Ab alias aut dicta dolor dolore ex, ipsum nobis officia pariatur quaerat quod quos!';
        $tickets = [new TicketInfo('FOO-0', $loremIpsum, 'https://google.com')];
        $message = self::$slack->getChatMessageThread($tickets, 'Nowhere', 'deploy-message-generator-test', [], []);
        self::$slack->sendMessage($message[0], new NullTransport());

        $threadTickets = [];
        for ($i = 0; $i < 100; $i++)
        {
            $threadTickets[] = new TicketInfo("FOO-$i", $loremIpsum, 'https://google.com');
        }

        $thread = self::$slack->getChatMessageThread($threadTickets, "Nowhere", "deploy-message-generator-test", [], []);
        self::$slack->sendThread($thread, new NullTransport());
    }

    public function testMessageTruncating () : void
    {
        // This is 3000+ characters
        $loremIpsum = "Lorem ipsum dolor sit amet, consectetur adipisicing elit. Culpa cum cupiditate deserunt dignissimos dolores ea est, explicabo hic in inventore labore molestias nam similique tenetur voluptates. Neque nesciunt numquam recusandae tenetur totam. Architecto at aut consequuntur deserunt dolore doloremque dolorum earum expedita, labore laudantium molestias, pariatur, possimus quibusdam quos ratione repellat sint soluta veniam! Adipisci architecto beatae commodi consequatur deserunt dolor ducimus eius et eveniet exercitationem hic id, ipsum itaque iusto magnam maxime nesciunt nulla numquam obcaecati odio odit optio pariatur possimus qui quod recusandae repellat reprehenderit sapiente sequi sunt veritatis voluptas voluptatem voluptates? Aspernatur cumque eius explicabo ipsam quos rem sint suscipit voluptatum. Accusantium animi aperiam beatae blanditiis, cum delectus deserunt dolor doloremque et eveniet explicabo facilis fuga harum in ipsa iure labore laboriosam laudantium minima modi nemo nulla officia omnis praesentium quae quisquam quod sapiente tempora velit veniam! Adipisci alias autem consequatur, debitis delectus doloribus ea earum esse illum incidunt inventore iure laboriosam maiores, minima modi nesciunt nihil nostrum, nulla pariatur quaerat quasi quo quos recusandae repellendus rerum sapiente sint sit tempora ullam ut velit vitae voluptatem voluptates. Amet atque consequuntur cum cupiditate debitis ea eaque enim eos error esse eveniet fugit in inventore ipsa itaque magnam natus nostrum odit placeat provident quam, quibusdam quidem quis quisquam quo quod repudiandae sapiente sed velit veniam vitae voluptas voluptatem, voluptates? Corporis dicta quasi vero. Accusamus adipisci at ea exercitationem fugit incidunt obcaecati odio perferendis possimus praesentium. Consequatur earum labore modi odio quo reprehenderit, sequi! Ad aperiam architecto consequatur corporis cumque dicta ea eaque, explicabo hic laboriosam, minima porro quia rerum sint tenetur unde, vel. Distinctio dolore natus perferendis sed. Corporis cum labore ullam veritatis. Consequatur delectus deserunt distinctio dolore dolorem error esse ex fuga incidunt iusto, nesciunt quaerat quasi quis, sequi sit, soluta tempore veniam! Dolorem facilis quo tenetur voluptatum. Accusantium alias dolorem doloremque explicabo illo laborum magni mollitia, natus nulla porro quasi suscipit tenetur veritatis. Architecto at beatae, doloribus earum est excepturi facilis, incidunt iusto, labore libero magnam maiores molestias nulla officia omnis perspiciatis repudiandae sit! Ducimus, nesciunt, nihil. Ab, eos esse excepturi magnam mollitia numquam tenetur. A accusantium amet, animi aspernatur at culpa cupiditate dicta distinctio dolor dolorem, eaque eius eligendi, et exercitationem facere hic iusto laborum minima modi molestiae odit pariatur perspiciatis praesentium quo rem repellat sapiente tempore temporibus totam veniam. Aliquam debitis eveniet expedita odit tempore. Accusantium aut dolorem et omnis quibusdam recusandae voluptate? Accusamus aliquam atque aut et fugit laboriosam laborum omnis, optio perspiciatis qui quod, repellat sed sunt temporibus totam ullam ut vel. Ab architecto asperiores assumenda commodi cumque cupiditate delectus dolor dolorem ea eveniet facere fugit impedit incidunt iste iusto, magnam non numquam odio optio porro praesentium provident quasi qui quo repellat repudiandae, rerum saepe sint soluta temporibus voluptate, voluptates voluptatibus voluptatum. A ab ad aperiam beatae blanditiis cupiditate deleniti deserunt distinctio doloremque ducimus ea esse excepturi facilis id, iure minima necessitatibus nobis optio praesentium, provident quibusdam rem voluptates? Ab alias aut dicta dolor dolore ex, ipsum nobis officia pariatur quaerat quod quos!";
        $tickets = [new TicketInfo("FOO-0", $loremIpsum, 'https://google.com')];

        $message = self::$slack->getChatMessageThread($tickets, "nowhere", "deploy-message-generator-test", [], []);
        $options = $message[0]->getOptions()->toArray();
        $blocks = $options["blocks"];
        $ticketBlock = $blocks[1];
        $textNode = $ticketBlock["text"];
        $text = $textNode["text"];

        self::assertEquals(3000, \strlen($text));
        self::assertEquals("...", \substr($text, 3000-3, 3));
    }
}
