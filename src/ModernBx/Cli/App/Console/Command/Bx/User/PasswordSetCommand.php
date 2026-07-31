<?php

declare(strict_types=1);

namespace ModernBx\Cli\App\Console\Command\Bx\User;

use ModernBx\Cli\App\Console\Command\Bx\KernelCommand;
use ModernBx\Cli\App\Console\Mixin\Common\IO;
use ModernBx\Cli\App\Service\ClassAliasLoader;
use ModernBx\Cli\App\Service\Remote\BitrixAdminClient;
use ModernBx\Cli\App\Service\Remote\RemotePhpTrait;
use ModernBx\Cli\App\Service\Remote\RemoteProjectConfigManager;
use ModernBx\Cli\App\Service\Remote\RemoteUserPhpCodeBuilder;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

final class PasswordSetCommand extends KernelCommand
{
    use IO;
    use RemotePhpTrait;

    protected static $defaultName = 'user:password-set';

    private RemoteUserPhpCodeBuilder $codeBuilder;

    public function __construct(
        ClassAliasLoader $aliasLoader,
        RemoteProjectConfigManager $remoteProjectConfigManager,
        BitrixAdminClient $bitrixAdminClient,
        RemoteUserPhpCodeBuilder $codeBuilder
    ) {
        parent::__construct($aliasLoader);
        $this->remoteProjectConfigManager = $remoteProjectConfigManager;
        $this->bitrixAdminClient = $bitrixAdminClient;
        $this->codeBuilder = $codeBuilder;
    }

    protected function configure(): void
    {
        $this->setDescription('Изменить пароль пользователя')
            ->setDefinition(new InputDefinition([
                new InputOption('remote', null, InputOption::VALUE_REQUIRED, 'Кодовое имя удаленного проекта'),
                new InputOption('local', null, InputOption::VALUE_NONE, 'Отключить неявный remote текущей сессии'),
                new InputOption('login', null, InputOption::VALUE_REQUIRED, 'Логин пользователя'),
                new InputOption('email', null, InputOption::VALUE_REQUIRED, 'Email пользователя'),
                new InputOption('id', null, InputOption::VALUE_REQUIRED, 'ID пользователя'),
            ]));
    }

    protected function executeInternal(InputInterface $input, OutputInterface $output): void
    {
        [$field, $value] = $this->getSelector($input);
        $password = $this->askPassword($input, $output);

        if ($password === '') {
            $password = $this->generatePassword();
            $this->printer->info('Сгенерированный пароль: ' . $password);
        }

        $remote = $input->getOption('remote');

        if (is_string($remote)) {
            $this->decodeRemoteJsonResult(
                $this->executeRemotePhp($remote, $this->codeBuilder->buildPasswordSet($field, $value, $password)),
                'Не удалось изменить пароль пользователя удаленного проекта.',
            );
            return;
        }

        parent::executeInternal($input, $output);
        $this->updateLocal($field, $value, $password);
    }

    /** @return array{string, string} */
    private function getSelector(InputInterface $input): array
    {
        $selectors = [];

        foreach (['login' => 'LOGIN', 'email' => 'EMAIL', 'id' => 'ID'] as $option => $field) {
            $value = $input->getOption($option);

            if (is_string($value) && $value !== '') {
                $selectors[] = [$field, $value];
            }
        }

        if (count($selectors) !== 1) {
            throw new \InvalidArgumentException(
                'Необходимо указать ровно одну из опций: --login, --email или --id.',
                static::CODE_INVALID_OPTION_VALUE,
            );
        }

        return $selectors[0];
    }

    private function askPassword(InputInterface $input, OutputInterface $output): string
    {
        $question = new Question('Новый пароль (Enter — сгенерировать): ');
        $question->setHidden(true);
        $question->setHiddenFallback(false);
        /** @var QuestionHelper $helper */
        $helper = $this->getHelper('question');

        $answer = $helper->ask($input, $output, $question);

        return is_string($answer) ? $answer : '';
    }

    private function generatePassword(): string
    {
        $groups = ['abcdefghijklmnopqrstuvwxyz', 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', '0123456789'];
        $alphabet = implode('', $groups);
        $characters = [];

        foreach ($groups as $group) {
            $characters[] = $group[random_int(0, strlen($group) - 1)];
        }

        while (count($characters) < 24) {
            $characters[] = $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        for ($index = count($characters) - 1; $index > 0; $index--) {
            $swap = random_int(0, $index);
            [$characters[$index], $characters[$swap]] = [$characters[$swap], $characters[$index]];
        }

        return implode('', $characters);
    }

    private function updateLocal(string $field, string $value, string $password): void
    {
        // CUser intentionally stays a string here. A direct class reference is rewritten by PHP-Scoper and makes
        // the scoped alias take part in loading the Bitrix prologue. Some Bitrix versions then fail while parsing
        // include.php, which breaks every local command before this method is even called.
        $userClass = implode('', ['C', 'User']);

        $users = $userClass::GetList($by = 'id', $order = 'asc', [$field => $value], ['FIELDS' => ['ID']]);
        $user = $users->Fetch();

        if (!is_array($user) || !isset($user['ID'])) {
            throw new \RuntimeException('Пользователь не найден.', static::CODE_INVALID_ARGUMENT_VALUE);
        }

        /** @phpstan-ignore-next-line */
        $updater = new $userClass();

        if (!$updater->Update((int) $user['ID'], ['PASSWORD' => $password, 'CONFIRM_PASSWORD' => $password])) {
            throw new \RuntimeException($updater->LAST_ERROR ?: 'Не удалось изменить пароль пользователя.');
        }
    }
}
