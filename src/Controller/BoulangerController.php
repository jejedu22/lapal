<?php

namespace App\Controller;

use App\Entity\Boulanger;
use App\Form\BoulangerType;
use App\Repository\BoulangerRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/boulanger')]
class BoulangerController extends AbstractController
{
    #[Route('/', name: 'boulanger_index', methods: ['GET'])]
    public function index(BoulangerRepository $boulangerRepository): Response
    {
        return $this->render('boulanger/index.html.twig', [
            'boulangers' => $boulangerRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'boulanger_new', methods: ['GET','POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $boulanger = new Boulanger();
        $form = $this->createForm(BoulangerType::class, $boulanger);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($boulanger);
            $entityManager->flush();
            $this->addFlash('success', 'Le boulanger a été ajouté.');

            return $this->redirectToRoute('boulanger_index');
        }

        return $this->render('boulanger/new.html.twig', [
            'boulanger' => $boulanger,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'boulanger_show', methods: ['GET'])]
    public function show(Boulanger $boulanger): Response
    {
        return $this->render('boulanger/show.html.twig', [
            'boulanger' => $boulanger,
        ]);
    }

    #[Route('/{id}/edit', name: 'boulanger_edit', methods: ['GET','POST'])]
    public function edit(Request $request, Boulanger $boulanger, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(BoulangerType::class, $boulanger);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('boulanger_index');
        }

        return $this->render('boulanger/edit.html.twig', [
            'boulanger' => $boulanger,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'boulanger_delete', methods: ['DELETE'])]
    public function delete(Request $request, Boulanger $boulanger, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$boulanger->getId(), $request->request->get('_token'))) {
            if (!$boulanger->getJourDistribs()->isEmpty()) {
                $this->addFlash('warning', 'Ce boulanger est associé à des jours de distribution : il ne peut pas être supprimé.');

                return $this->redirectToRoute('boulanger_edit', ['id' => $boulanger->getId()]);
            }
            $entityManager->remove($boulanger);
            $entityManager->flush();
            $this->addFlash('success', 'Le boulanger a été supprimé.');
        }

        return $this->redirectToRoute('boulanger_index');
    }
}
