import { Link } from "@inertiajs/react";

const decodeHtml = (value) =>
    String(value ?? "")
        .replace(/&laquo;/g, "«")
        .replace(/&raquo;/g, "»")
        .replace(/&amp;/g, "&")
        .replace(/&#039;/g, "'")
        .replace(/&quot;/g, '"');

export default function Pagination({ links = [], currentPage = 1 }) {
    const resolvedCurrentPage = Number(currentPage ?? 1);

    const visibleLinks = links.filter((link) => {
        const normalizedLabel = decodeHtml(link.label).trim();
        const pageNumber = Number(normalizedLabel);
        const isNavigationLink =
            normalizedLabel.includes("«") || normalizedLabel.includes("»");

        if (isNavigationLink || link.active) {
            return true;
        }

        if (Number.isNaN(pageNumber)) {
            return false;
        }

        return Math.abs(pageNumber - resolvedCurrentPage) <= 1;
    });

    return (
        <div className="mt-6 mb-6 flex justify-center space-x-2">
            {visibleLinks.map((link, index) => (
                <Link
                    key={`${decodeHtml(link.label)}-${index}`}
                    href={link.url || "#"}
                    as="button"
                    className={`rounded-md border px-4 py-1 transition-colors duration-300 ${
                        link.active
                            ? "cursor-default border-blue-600 bg-blue-600 text-white"
                            : "border-gray-300 bg-white text-gray-700 hover:bg-gray-100 hover:text-blue-600"
                    } ${!link.url ? "cursor-not-allowed opacity-50" : ""}`}
                    onClick={(event) => !link.url && event.preventDefault()}
                >
                    {decodeHtml(link.label).trim()}
                </Link>
            ))}
        </div>
    );
}
