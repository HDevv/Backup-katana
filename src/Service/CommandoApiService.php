<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Service d'appel au webservice Commando.
 * Gère l'authentification (timestamp + token MD5) et les requêtes HTTP.
 */
class CommandoApiService
{
    private string $urlBase;
    private string $clePrivee;

    public function __construct(
        private HttpClientInterface $httpClient,
        string $commandoUrlBase,
        string $commandoClePrivee
    ) {
        $this->urlBase = $commandoUrlBase;
        $this->clePrivee = $commandoClePrivee;
    }

    /**
     * Génère un timestamp au format AAAAMMJJHHMMSS.
     */
    private function generateTimestamp(): string
    {
        return (new \DateTimeImmutable())->format('YmdHis');
    }

    /**
     * Calcule le token MD5 selon la logique Commando.
     * Ordre: ClePrivee + param1=value1 + param2=value2 + ... + ts=TIMESTAMP
     * 
     * @param array $params Paramètres dans l'ordre exact requis (sans ts/token)
     * @param string $timestamp
     * @return string
     */
    private function calculateToken(array $params, string $timestamp): string
    {
        $canonical = $this->clePrivee;
        foreach ($params as $key => $value) {
            $canonical .= $key . '=' . $value;
        }
        $canonical .= 'ts=' . $timestamp;
        
        return md5($canonical);
    }

    /**
     * Appelle le webservice Commando avec authentification automatique.
     * 
     * @param array $params Paramètres de la requête dans l'ordre (act, cli, ref, etc.)
     * @return array Réponse JSON décodée
     * @throws \Exception
     */
    private function callApi(array $params): array
    {
        $timestamp = $this->generateTimestamp();
        $token = $this->calculateToken($params, $timestamp);

        // Ajout de ts et token aux paramètres
        $queryParams = array_merge($params, [
            'ts' => $timestamp,
            'token' => $token
        ]);

        try {
            $response = $this->httpClient->request('GET', $this->urlBase, [
                'query' => $queryParams,
                'timeout' => 30,
            ]);

            $statusCode = $response->getStatusCode();
            if ($statusCode !== 200) {
                throw new \Exception("Erreur HTTP {$statusCode}: " . $response->getContent(false));
            }

            $content = $response->getContent();
            $data = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Erreur décodage JSON: ' . json_last_error_msg());
            }

            return $data ?? [];
        } catch (\Throwable $e) {
            throw new \Exception('Erreur appel Commando API: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Récupère la liste des CSC pour un client.
     * act=cscliste&cli=XXXXX
     * 
     * @param string $codeClient Code client (ex: 00318945)
     * @return array Liste des CSC
     */
    public function getCscListe(string $codeClient): array
    {
        return $this->callApi([
            'act' => 'cscliste',
            'cli' => $codeClient,
        ]);
    }

    /**
     * Construit l'URL complète pour un appel API (pour affichage/debug)
     * 
     * @param array $params Paramètres de l'appel
     * @return string URL complète avec token
     */
    public function buildUrl(array $params): string
    {
        $timestamp = $this->generateTimestamp();
        $token = $this->calculateToken($params, $timestamp);
        
        $params['ts'] = $timestamp;
        $params['token'] = $token;
        
        return $this->urlBase . '?' . http_build_query($params);
    }

    /**
     * Récupère les reliquats pour un client et des références produits.
     * act=reliq&cli=XXXXX&ref=REF1,REF2,REF3
     * 
     * @param string $codeClient
     * @param string $references Liste de références séparées par des virgules
     * @return array Tableau [reference => quantite]
     */
    public function getReliquats(string $codeClient, string $references = ''): array
    {
        return $this->callApi([
            'act' => 'reliq',
            'cli' => $codeClient,
            'ref' => $references,
        ]);
    }

    /**
     * Récupère les stocks disponibles pour des références.
     * act=dispo&ref=REF1,REF2,REF3
     * 
     * @param string $references Liste de références séparées par des virgules
     * @param bool $withAlert Inclure les infos d'alerte dispo
     * @return array
     */
    public function getStocksDispos(string $references, bool $withAlert = false): array
    {
        $params = [
            'act' => 'dispo',
            'ref' => $references,
        ];

        if ($withAlert) {
            $params['alert'] = '1';
        }

        return $this->callApi($params);
    }

    /**
     * Récupère les tarifs pour un client et des références.
     * act=tarif&cli=XXXXX&ref=REF1,REF2,REF3
     * 
     * @param string $codeClient
     * @param string $references
     * @param bool $fullInfo Inclure les détails du calcul tarifaire
     * @return array
     */
    public function getTarifs(string $codeClient, string $references, bool $fullInfo = false): array
    {
        $params = [
            'act' => 'tarif',
            'cli' => $codeClient,
            'ref' => $references,
        ];

        if ($fullInfo) {
            $params['fullinfo'] = '1';
        }

        return $this->callApi($params);
    }
}
