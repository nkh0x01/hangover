// Gadget / machinery definitions across Tier 1–3.
// `station` maps a gadget to a chain step.
// `kind` is one of: 'station' | 'globalMult' | 'goldMult' | 'tier3'.
export const gadgets = [
  // Tier 1 — stations
  { id: 'doughMixer', nameKey: 'g_dough_mixer', icon: '🌀', tier: 1, kind: 'station', station: 'dough', baseCost: 60, baseRate: 1.0 },
  { id: 'meatGrinder', nameKey: 'g_meat_grinder', icon: '⚙️', tier: 1, kind: 'station', station: 'meat', baseCost: 60, baseRate: 1.0 },
  { id: 'herbChopper', nameKey: 'g_herb_chopper', icon: '🔪', tier: 1, kind: 'station', station: 'herb', baseCost: 120, baseRate: 1.2 },

  // Tier 2 — stations + global tech
  { id: 'pleatingRobot', nameKey: 'g_pleating_robot', icon: '🤖', tier: 2, kind: 'station', station: 'pleat', baseCost: 1500, baseRate: 6 },
  { id: 'steamCauldron', nameKey: 'g_steam_cauldron', icon: '🍲', tier: 2, kind: 'station', station: 'boil', baseCost: 12000, baseRate: 30 },
  { id: 'autoSeller', nameKey: 'g_auto_seller', icon: '💱', tier: 2, kind: 'goldMult', effect: 0.1, baseCost: 8000 }, // +10% gold/khinkali per level
  { id: 'conveyor', nameKey: 'g_conveyor', icon: '🛤️', tier: 2, kind: 'globalMult', effect: 0.05, baseCost: 50000 }, // +5% global per level

  // Tier 3 — heavy machinery (investor-funded). Each level multiplies machineRate.
  { id: 'assemblyLine', nameKey: 'g_assembly_line', icon: '🏭', tier: 3, kind: 'tier3', mult: 0.5, baseCost: 5000000 }, // +50% machineRate per level
  { id: 'roboticKitchen', nameKey: 'g_robotic_kitchen', icon: '🦾', tier: 3, kind: 'tier3', mult: 2.0, baseCost: 250000000 }, // +200% machineRate per level
];

// Quick lookups.
export const gadgetById = gadgets.reduce((acc, g) => {
  acc[g.id] = g;
  return acc;
}, {});

export default gadgets;
