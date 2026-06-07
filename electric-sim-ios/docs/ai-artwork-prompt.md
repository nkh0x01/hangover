# AI-სურათების პრომპტი კომპონენტებისთვის

ამ ფაილის მიხედვით დააგენერირე კომპონენტების სურათები (Midjourney / DALL·E / SDXL და ა.შ.),
შემდეგ ჩასვი `ElectricSim/App/Assets.xcassets`-ში **ზუსტი სახელებით** (იხ. ცხრილი).
აპი ავტომატურად აჩვენებs ფოტოს, თუ ასეთი იმიჯ-სეტი არსებობს; თუ არა — SF Symbol-ს.

## სტილის მთავარი პრომპტი (ყველა კომპონენტისთვის ერთნაირი)

> **EN (image AI-სთვის):**
> "Flat vector icon of a {COMPONENT}, modern minimalist style, front view, centered,
> on a pure transparent background, soft long shadow, consistent thin outline,
> blue (#2A7FF5) and grey palette with subtle yellow accents, electrical DIN-rail
> component look, clean and professional, app icon style, 1:1 square, 1024x1024,
> no text, no labels, no watermark."

თითო კომპონენტისთვის `{COMPONENT}` შეცვალე ქვემოთ მოცემული აღწერით.
**მნიშვნელოვანია:** ერთიანი სტილი, **გამჭვირვალე ფონი (PNG)**, კვადრატი 1024×1024,
ცენტრში, წარწერების გარეშე.

## კომპონენტები (აღწერა + ფაილის სახელი)

| აღწერა `{COMPONENT}` (EN) | Asset სახელი (.imageset) |
|---|---|
| single-phase miniature circuit breaker (MCB), 1-pole, white DIN module with black toggle lever | `comp_mcb` |
| motor protection circuit breaker (MPCB), 3-pole, rotary dial front | `comp_mpcb` |
| residual current device (RCD), 2-pole, with test button | `comp_rcd` |
| RCBO combined breaker, 1-pole module | `comp_rcbo` |
| main isolator switch, large toggle, DIN module | `comp_mainSwitch` |
| surge protection device (SPD), with status window | `comp_spd` |
| modular contactor, DIN rail | `comp_contactor` |
| modular relay with coil symbol | `comp_relay` |
| wall light switch, single rocker | `comp_lightSwitch` |
| copper comb busbar / phase rail | `comp_busbar` |
| Wago lever connector, 5-port, orange levers | `comp_wago` |
| incandescent light bulb glowing | `comp_lamp` |
| dimmable light with dimmer knob | `comp_dimmer` |
| European wall power socket (type F / Schuko) | `comp_socket` |
| three-phase industrial socket (red CEE) | `comp_socket3ph` |
| electric water heater / boiler tank | `comp_boiler` |
| electric oven, front view | `comp_oven` |
| electric convector heater | `comp_heater` |
| air conditioner indoor unit | `comp_airConditioner` |
| three-phase electric motor with fan | `comp_motor` |
| smart wifi wall switch | `comp_smartSwitch` |
| smart wifi relay module | `comp_smartRelay` |
| smart wifi dimmer module | `comp_smartDimmer` |
| smart energy meter with display | `comp_smartMeter` |
| incoming power supply / meter box | `comp_supply` |

## როგორ ჩავსვა Xcode-ში
1. Xcode → `Assets.xcassets` → მარჯვენა click → **New Image Set**.
2. სახელი დაარქვი ზუსტად ცხრილის მიხედვით (მაგ. `comp_mcb`).
3. ჩააგდე PNG (1x/2x/3x ან ერთი ვექტორ-PDF „Single Scale"-ით).
4. გაუშვი აპი — ბანქოებზე ფოტო ავტომატურად გამოჩნდება.

> 💡 რჩევა: დააგენერირე ერთ batch-ში ერთი და იგივე სტილით, რომ ვიზუალი ერთგვაროვანი იყოს.
