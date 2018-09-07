<?php

namespace MainBundle\Controller;

use MainBundle\Entity\Langage;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;

use MainBundle\Entity\Execution;
use MainBundle\Form\ExecutionType;
use MainBundle\Form\OptionsInterfaceType;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

class DefaultController extends Controller
{

    private function matchLanguageToAce($id)
    {
        //CODE TEMPORAIRE TODO EXTRAIRE DANS UN FICHIER JSON
        $em = $this->getDoctrine()->getManager();
        $name = $em->getRepository('MainBundle:Langage')->find($id)->getNom();

        $name = strtoupper($name);

        switch ($name) {
            case 'C++':
                return 'ace/mode/c_cpp';
                break;
            case 'C':
                return 'ace/mode/c_cpp';
                break;
            case 'JAVA':
                return 'ace/mode/java';
            default:
                return 'ace/mode/plain_text';
                break;
        }
    }

    /**
     * Create the IDE page
     * @param Request $request
     * @return Response
     */
    public function indexAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $logger = $this->get('logger');              

        // Création formulaire paramètrage interface
        $user = $this->getUser();
        $formInterface = $this->createform(OptionsInterfaceType::class, $user);

        // Récupération des langages
        $langages = $em->getRepository('MainBundle:Langage')->findByActif(true);  

        // Récupération du code en session
        $jsonFiles = $request->getSession()->get('files'.$user->getId());

        // Récupération du langage
        $langageID = $request->getSession()->get('langage'.$user->getId());
        if ($langageID == null) {
            //if no langage specified take the first in database
            /** @var Langage $selected_langage */
            $selected_langage = $langages[0];
            $langageID = $selected_langage->getId();
        }
        $langage = $em->getRepository('MainBundle:Langage')->find($langageID);
        $info = $this->getLanguageInfo($langageID);
        $logger->info(print_r($info, true));

        // Création du formulaire d'exécution
        $exec = new Execution();
        $exec->setCompilationOptions($langage->getOptions());
        $formExecution = $this->createform(ExecutionType::class, $exec);

        return $this->render('MainBundle:Default:index.html.twig', array(
            'list_langage' => $langages,
            'selected_langage_name' => $info['name'],
            'form' => $formExecution->createView(),
            'formInterface' => $formInterface->createView(),
            'jsonFiles' => json_encode($jsonFiles),
            'langage' => $langageID
        ));
    }

    /**
     * Retrieve information about the language with the given id
     * @param $id
     * @return array
     */
    private function getLanguageInfo($id)
    {
        $em = $this->getDoctrine()->getManager();

        $lang = $em->getRepository('MainBundle:Langage')->find($id);

        $name = $lang->getNom();
        $compilateur = $lang->getCompilateur();
        $options = $lang->getOptions();

        /** @noinspection PhpUndefinedMethodInspection */
        $details = $em->getRepository('MainBundle:DetailLangage')->findByLangage($id);

        $detailThatMatter = array();
        foreach ($details as $d) {
            /** @noinspection PhpUndefinedMethodInspection */
            $detailThatMatter[] = array(
                'ext' => $d->getExtension(),
                'model' => $d->getModele()
            );
        }

        $logger = $this->get('logger');
        $logger->info(print_r($detailThatMatter, true));

        return array(
            'ace' => $this->matchLanguageToAce($id),
            'modeles' => $detailThatMatter,
            'name' => $name,
            'compilateur' => $compilateur,
            'options' => $options
        );
    }

    /**
     * Renvoie les info du langage sous forme d'un json contenant
     *   'ace' -> paramètre pour l'editeur
     *   'model' -> fichier modèle pour le langage
     * @param Request $request
     * @return JsonResponse|Response
     */
    public function languageInfoAction(Request $request)
    {        
        if ($request->isXMLHttpRequest()) {
            $id = $request->request->get('lang');
            $info = $this->getLanguageInfo($id);

            return new JsonResponse($info);
        }
        return new Response('This is not ajax!', 400);
    }

    /**
     * Save the user's files in session
     * @param Request $request
     * @return JsonResponse|Response
     */
    public function saveCodeAction(Request $request)
    {
        if ($request->isXMLHttpRequest()) {
            $userID = $this->getUser()->getId();
            $jsonFiles = $request->request->get('files');
            $request->getSession()->set('files'.$userID, json_decode($jsonFiles));

            $langage = $request->request->get('langage');
            $request->getSession()->set('langage'.$userID, $langage);


            return new JsonResponse("OK");
        }
        return new Response('This is not ajax!', 400);
    }

    /**
     * Save the user interface configuration in database
     * @param Request $request
     * @return JsonResponse|Response
     */
    public function updateInterfaceAction(Request $request)
    {
        if ($request->isXMLHttpRequest()) {

            $user = $this->getUser();
            $form= $this->createform(OptionsInterfaceType::class, $user);
            $form->handleRequest($request);

            if ($form->isValid()) {
                $em = $this->getDoctrine()->getManager();
                $em->persist($user);
                $em->flush();
                $rep = "OK";
            } else {
                $rep = "Formulaire non valide";
            }        
            
            return new JsonResponse($rep);
        }
        return new Response('This is not ajax!', 400);
    }

}
