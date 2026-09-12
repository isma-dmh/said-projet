<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:create-admin')]
class CreateAdminCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED)
            ->addArgument('password', InputArgument::REQUIRED)
            ->addArgument('firstname', InputArgument::REQUIRED)
            ->addArgument('lastname', InputArgument::REQUIRED);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // À toi de compléter :
        // 1. Créer un new User()
        $user = new User();
        // 2. Remplir email/firstname/lastname
        $user->setEmail($input->getArgument("email"));
        $user->setFirstname($input->getArgument("firstname"));
        $user->setLastname($input->getArgument("lastname"));
        // 3. Hasher le mot de passe avec $this->passwordHasher (PAS setPassword($password) en clair !)
        $user->setPassword($this->passwordHasher->hashPassword($user, $input->getArgument("password")));
        // 4. persist + flush
        $this->em->persist($user);
        $this->em->flush();
        // 5. Confirmer en console
        $output->writeln(sprintf('Utilisateur admin créé : %s', $user->getEmail()));
        return Command::SUCCESS;
    }
}
