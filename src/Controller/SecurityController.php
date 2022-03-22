<?php


namespace App\Controller;


use BaconQrCode\Renderer\Image\ImagickImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use PragmaRX\Google2FA\Google2FA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    public const QR_CODE_KEY = '_qr_code_secret';

    /**
     * @Route("/login", name="app_login")
     */
    public function login(AuthenticationUtils $authenticationUtils)
    {
        // get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();
        // last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', ['last_username' => $lastUsername, 'error' => $error]);
    }

    /**
     * @Route("/setup-2FA", name="app_security_setup_fa")
     */
    public function setup(SessionInterface $session, AuthenticationUtils $authenticationUtils)
    {
        $error = $authenticationUtils->getLastAuthenticationError();

        $google2fa = new Google2FA();
        if (!$session->has(self::QR_CODE_KEY)) {
            $secretKey = $google2fa->generateSecretKey();
            $session->set(self::QR_CODE_KEY, $secretKey);
        } else {
            $secretKey = $session->get(self::QR_CODE_KEY);
        }
        //Generate QR CODE based on secretKey
        $qrCodeUrl = $google2fa->getQRCodeUrl(
            'Clinique LE CHALET',
            'epsi.fr',
            $secretKey
        );
        $writer = new Writer(
            new ImageRenderer(
                new RendererStyle(400),
                new ImagickImageBackEnd()
            )
        );
        $qrCodeImage = base64_encode($writer->writeString($qrCodeUrl));

        return $this->render('security/setup-2fa.html.twig', [
            'qrCodeImage' => $qrCodeImage,
            'secretKey' => $secretKey,
            'error' => $error,
        ]);
    }

    /**
     * @Route("/2FA-protected", name="app_security_authentification_protected")
     */
    public function authentificationProtected()
    {
        return $this->render('security/protected.html.twig');
    }
}
