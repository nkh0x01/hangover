// Ingredient supply definitions.
// Each purchased level adds `baseSupply` to that ingredient's supply rate.
// level 0 already provides base supply so the line isn't dead at zero gadgets.
export const ingredients = [
  { id: 'flour', nameKey: 'ing_flour', icon: '🌾', baseCost: 30, baseSupply: 2.0, perKhinkali: 1.0 },
  { id: 'meat', nameKey: 'ing_meat', icon: '🥩', baseCost: 45, baseSupply: 2.0, perKhinkali: 1.0 },
  { id: 'herbs', nameKey: 'ing_herbs', icon: '🌿', baseCost: 25, baseSupply: 1.5, perKhinkali: 0.5 },
];

export default ingredients;
