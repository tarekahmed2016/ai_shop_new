export function buildCategoryForest(categories = []) {
  const nodesById = new Map()

  categories.forEach((category) => {
    nodesById.set(category.id, {
      ...category,
      children: [],
    })
  })

  const roots = []

  categories.forEach((category) => {
    const node = nodesById.get(category.id)
    const parent = category.parent_id ? nodesById.get(category.parent_id) : null

    if (parent) {
      parent.children.push(node)
      return
    }

    roots.push(node)
  })

  return roots
}
