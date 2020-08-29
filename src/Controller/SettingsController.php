<?php

namespace App\Controller;

use App\Entity\Settings;
use App\Form\SettingsType;
use App\Repository\SettingsRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/settings")
 */
class SettingsController extends AbstractController
{
    /**
     * @Route("/", name="settings_index", methods={"GET"})
     */
    public function index(SettingsRepository $settingsRepository): Response
    {
        return $this->render('settings/index.html.twig', [
            'settings' => $settingsRepository->findAll(),
        ]);
    }


    /**
     * @Route("/{id}/edit", name="settings_edit", methods={"GET","POST"})
     */
    public function edit(Request $request, Settings $setting): Response
    {
        $form = $this->createForm(SettingsType::class, $setting);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('settings_index');
        }

        return $this->render('settings/edit.html.twig', [
            'setting' => $setting,
            'form' => $form->createView(),
        ]);
    }

}
