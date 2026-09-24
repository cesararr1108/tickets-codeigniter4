import { apiGet, toList } from "./api.js";

/*
 * GET /categories
 * Devuelve: [{ id, name }]
 */
export async function obtenerCategorias(apiUrl) {

    const result = await apiGet(apiUrl, "/categories");

    return toList(result).map(category => ({
        id: category.IdCategory,
        name: category.Category ?? "Categoría"
    }));
}

/*
 * GET /subcategories/category/{idCategory}
 * Devuelve: [{ id, name }]
 */
export async function obtenerSubcategorias(apiUrl, categoryId) {

    const result = await apiGet(
        apiUrl,
        "/subcategories/category/" + encodeURIComponent(categoryId)
    );

    return toList(result).map(sub => ({
        id: sub.IdSubCategory,
        name: sub.SubCategory ?? "Subcategoría"
    }));
}

/*
 * GET /subcategories
 * Devuelve todas: [{ id, name, categoryId }]
 * Se usa para mostrar un resumen dentro de cada card de categoría.
 */
export async function obtenerTodasSubcategorias(apiUrl) {

    const result = await apiGet(apiUrl, "/subcategories");

    return toList(result).map(sub => ({
        id: sub.IdSubCategory,
        name: sub.SubCategory ?? "Subcategoría",
        categoryId: sub.IdCategory
    }));
}
