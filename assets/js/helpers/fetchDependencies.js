import axios from 'axios';

export async function fetchDependencies(depUrlsEntries) {
    const results = await axios.all(
        depUrlsEntries.map(([_, depUrlCat]) => axios.get(depUrlCat.depUrl))
    );

    const deps = {};

    results.forEach((response, index) => {
        const data = response.data;
        if (data.length > 0) {
            const [category, depUrlCat] = depUrlsEntries[index];
            deps[category] = {
                list: data,
                ...(depUrlCat.url && { url: depUrlCat.url }),
                ...(depUrlCat.urlIdentifier && { urlIdentifier: depUrlCat.urlIdentifier }),
            };
        }
    });

    return deps;
}
