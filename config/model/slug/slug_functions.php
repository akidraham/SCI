<?php
// Model: slug_functions.php
require_once __DIR__ . '/../../../vendor/autoload.php';

// Use the Slugify library for generating SEO-friendly slugs
use Cocur\Slugify\Slugify;

class SlugService
{
    /** @var PDO Database connection instance */
    private PDO $pdo;

    /** @var Slugify Library instance to generate slugs */
    private Slugify $slugify;

    /** @var string Environment flag (e.g., 'local' or 'production') */
    private string $env;

    /**
     * Class constructor to initialize SlugService dependencies.
     *
     * @param PDO    $pdo Database connection
     * @param string $env Environment identifier
     */
    public function __construct(PDO $pdo, string $env = 'local')
    {
        $this->pdo = $pdo;
        $this->slugify = new Slugify();
        $this->env = $env;
    }

    /**
     * Generates a unique and SEO-friendly slug for promo title.
     * It appends a counter if the base slug already exists in the database.
     *
     * @param string   $promoTitle Title of the promo to be slugified
     * @param int|null $currentId  Optional promo ID to exclude from duplicate check (used in update mode)
     * @return string              A unique slug
     */
    public function generatePromoSlug(string $promoTitle, ?int $currentId = null): string
    {
        $baseSlug = $this->slugify->slugify($promoTitle); // Convert title to base slug
        $slug = $baseSlug;
        $counter = 1;
        $maxAttempts = 100;

        while ($maxAttempts-- > 0) {
            // Prepare SQL query to check for slug uniqueness
            $query = 'SELECT COUNT(*) FROM promos WHERE slug = :slug';
            $params = [':slug' => $slug];

            // If updating, exclude the current record from duplicate check
            if ($currentId !== null) {
                $query .= ' AND promo_id != :id';
                $params[':id'] = $currentId;
            }

            try {
                $stmt = $this->pdo->prepare($query); // Prepare query
                $stmt->execute($params);             // Execute with parameters
            } catch (PDOException $e) {
                // Handle database errors gracefully
                handleError("Database error while generating slug: " . $e->getMessage(), $this->env);
            }

            if ((int) $stmt->fetchColumn() === 0) {
                // Found unique slug → return it
                return $slug;
            }

            // Slug exists → append counter and retry
            $slug = $baseSlug . '-' . $counter++;
        }

        // All attempts failed → fallback handler
        handleError('Unable to generate a unique promo slug after 100 attempts.', $this->env);
    }
}
